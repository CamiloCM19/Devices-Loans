from __future__ import annotations

import json

from django.conf import settings
from django.contrib import messages
from django.db import transaction
from django.http import HttpRequest, HttpResponse, JsonResponse
from django.shortcuts import get_object_or_404, redirect, render
from django.urls import reverse
from django.utils import timezone
from django.views.decorators.csrf import csrf_exempt
from django.views.decorators.http import require_GET, require_POST

from .forms import CameraRegistrationForm, StudentRegistrationForm
from .models import Camara, Estudiante, HardwareTelemetrySession, LogPrestamo
from .services import (
    clear_current_student,
    ensure_bridge_session,
    find_camara_by_nfc,
    find_estudiante_by_nfc,
    get_bridge_active_student,
    get_current_student,
    normalize_nfc_id,
    set_current_student,
    telemetry_recorder,
    update_bridge_active_student,
)


def root_redirect(request: HttpRequest) -> HttpResponse:
    return redirect("inventory:index")


@require_GET
def inventory_index(request: HttpRequest) -> HttpResponse:
    camaras = Camara.objects.all()
    estudiante_actual = get_current_student(request)
    latest_bridge_session = HardwareTelemetrySession.objects.filter(session_type="bridge").first()
    return render(
        request,
        "inventory/index.html",
        {
            "camaras": camaras,
            "estudiante_actual": estudiante_actual,
            "latest_bridge_session": latest_bridge_session,
        },
    )


@require_POST
def process_inventory_scan(request: HttpRequest) -> HttpResponse:
    input_value = normalize_nfc_id(request.POST.get("nfc_id"))
    telemetry_context = {
        "telemetry_session_uuid": request.POST.get("telemetry_session_uuid", ""),
        "source": "inventory-web",
        "flow": "web_scan",
        "input": input_value,
        "uid_raw": request.POST.get("nfc_id"),
    }

    _record_web_event(
        telemetry_context,
        "backend.web_scan.received",
        "El backend recibio un escaneo desde la pagina web.",
        {"uid_normalized": input_value},
    )

    if not input_value:
        _record_web_event(
            telemetry_context,
            "backend.web_scan.invalid_input",
            "La pagina envio un UID vacio o invalido.",
            {"uid_normalized": input_value},
            "warning",
        )
        messages.error(request, "Codigo vacio o invalido.")
        return redirect("inventory:index")

    estudiante = find_estudiante_by_nfc(input_value)
    if estudiante is not None:
        set_current_student(request, estudiante)
        _record_web_event(
            telemetry_context,
            "backend.web_scan.student_ok",
            f"Se reconocio al estudiante {estudiante.nombre}.",
            {
                "uid_normalized": input_value,
                "student_id": estudiante.id,
                "student_name": estudiante.nombre,
            },
        )
        messages.success(request, f"Hola {estudiante.nombre}, escanea una camara.")
        return redirect("inventory:index")

    camara = find_camara_by_nfc(input_value)
    if camara is not None:
        estudiante_actual = get_current_student(request)
        if estudiante_actual is None:
            _record_web_event(
                telemetry_context,
                "backend.web_scan.student_required",
                "Se detecto una camara, pero no habia estudiante activo en sesion web.",
                {
                    "uid_normalized": input_value,
                    "camera_id": camara.id,
                    "camera_model": camara.modelo,
                },
                "warning",
            )
            messages.error(request, "Escanea primero tu carnet.")
            return redirect("inventory:index")

        response_message = _handle_camera_transition(camara, estudiante_actual)
        if response_message is None:
            _record_web_event(
                telemetry_context,
                "backend.web_scan.camera_unavailable",
                f"La camara {camara.modelo} esta en mantenimiento.",
                {
                    "uid_normalized": input_value,
                    "camera_id": camara.id,
                    "camera_model": camara.modelo,
                    "camera_state": camara.estado,
                },
                "warning",
            )
            messages.error(request, "La camara esta en mantenimiento.")
            return redirect("inventory:index")

        clear_current_student(request)
        event_type, message_text = response_message
        _record_web_event(
            telemetry_context,
            event_type,
            message_text,
            {
                "uid_normalized": input_value,
                "student_id": estudiante_actual.id,
                "student_name": estudiante_actual.nombre,
                "camera_id": camara.id,
                "camera_model": camara.modelo,
                "camera_state": camara.estado,
            },
        )
        messages.success(request, message_text.split(".")[0])
        return redirect("inventory:index")

    _record_web_event(
        telemetry_context,
        "backend.web_scan.unregistered",
        "El tag escaneado desde la pagina no esta registrado.",
        {
            "uid_normalized": input_value,
            "register_path": reverse("inventory:register", args=[input_value]),
        },
        "warning",
    )
    return redirect("inventory:register", nfc_id=input_value)


@csrf_exempt
@require_POST
def process_inventory_scan_rfid(request: HttpRequest) -> JsonResponse:
    payload = _json_body(request)
    configured_token = settings.RFID_BRIDGE_TOKEN or settings.RFID_READER_TOKEN
    provided_token = str(payload.get("token", "")).strip()
    source = str(payload.get("source", "usb-reader")).strip() or "usb-reader"
    bridge_session_uuid = str(payload.get("bridge_session_uuid", "")).strip()
    bridge_metadata = {
        "bridge_transport": payload.get("bridge_transport"),
        "bridge_mode": payload.get("bridge_mode"),
        "bridge_host": payload.get("bridge_host"),
        "bridge_pid": payload.get("bridge_pid"),
    }

    ensure_bridge_session(bridge_session_uuid, source, bridge_metadata)

    if configured_token and configured_token != provided_token:
        _record_bridge_event(
            bridge_session_uuid,
            source,
            "backend.rfid_scan.unauthorized",
            "Se rechazo una lectura RFID por token invalido.",
            {"ip": request.META.get("REMOTE_ADDR")},
            "warning",
            bridge_metadata,
        )
        return JsonResponse({"ok": False, "status": "unauthorized", "message": "Token invalido."}, status=401)

    raw = payload.get("uid") or payload.get("nfc_id") or payload.get("tag")
    input_value = normalize_nfc_id(raw)

    _record_bridge_event(
        bridge_session_uuid,
        source,
        "backend.rfid_scan.received",
        "El backend recibio una lectura RFID desde el bridge USB.",
        {
            "uid_raw": raw,
            "uid_normalized": input_value,
            "ip": request.META.get("REMOTE_ADDR"),
        },
        "info",
        bridge_metadata,
    )

    if not input_value:
        _record_bridge_event(
            bridge_session_uuid,
            source,
            "backend.rfid_scan.invalid_input",
            "El bridge envio un UID vacio o invalido.",
            {"uid_raw": raw, "uid_normalized": input_value},
            "warning",
            bridge_metadata,
        )
        return JsonResponse({"ok": False, "status": "invalid_input", "message": "UID vacio o invalido."}, status=422)

    estudiante = find_estudiante_by_nfc(input_value)
    if estudiante is not None:
        update_bridge_active_student(source, estudiante, bridge_session_uuid)
        _record_bridge_event(
            bridge_session_uuid,
            source,
            "backend.rfid_scan.student_ok",
            f"Se reconocio al estudiante {estudiante.nombre} desde el lector RFID.",
            {
                "uid_normalized": input_value,
                "student_id": estudiante.id,
                "student_name": estudiante.nombre,
            },
            "info",
            bridge_metadata,
        )
        return JsonResponse(
            {
                "ok": True,
                "status": "student_ok",
                "message": f"Hola {estudiante.nombre}, escanea una camara.",
                "student": {"id": estudiante.id, "nombre": estudiante.nombre},
            }
        )

    camara = find_camara_by_nfc(input_value)
    if camara is not None:
        estudiante_actual = get_bridge_active_student(source)
        if estudiante_actual is None:
            _record_bridge_event(
                bridge_session_uuid,
                source,
                "backend.rfid_scan.student_required",
                "Se detecto una camara por RFID, pero no habia estudiante activo en contexto.",
                {"uid_normalized": input_value, "camera_id": camara.id, "camera_model": camara.modelo},
                "warning",
                bridge_metadata,
            )
            return JsonResponse(
                {"ok": False, "status": "student_required", "message": "Escanea primero el carnet del estudiante."},
                status=409,
            )

        response_message = _handle_camera_transition(camara, estudiante_actual)
        if response_message is None:
            _record_bridge_event(
                bridge_session_uuid,
                source,
                "backend.rfid_scan.camera_unavailable",
                f"La camara {camara.modelo} esta en mantenimiento para flujo RFID.",
                {
                    "uid_normalized": input_value,
                    "camera_id": camara.id,
                    "camera_model": camara.modelo,
                    "camera_state": camara.estado,
                },
                "warning",
                bridge_metadata,
            )
            return JsonResponse(
                {"ok": False, "status": "camera_unavailable", "message": "La camara esta en mantenimiento."},
                status=409,
            )

        update_bridge_active_student(source, None, bridge_session_uuid)
        event_type, message_text = response_message
        _record_bridge_event(
            bridge_session_uuid,
            source,
            event_type.replace("backend.web_scan", "backend.rfid_scan"),
            message_text.replace("flujo web", "lector RFID"),
            {
                "uid_normalized": input_value,
                "student_id": estudiante_actual.id,
                "student_name": estudiante_actual.nombre,
                "camera_id": camara.id,
                "camera_model": camara.modelo,
                "camera_state": camara.estado,
            },
            "info",
            bridge_metadata,
        )
        status = "loan_ok" if camara.estado == Camara.Estado.PRESTADA else "return_ok"
        return JsonResponse({"ok": True, "status": status, "message": message_text.split(".")[0]})

    register_path = reverse("inventory:register", args=[input_value])
    _record_bridge_event(
        bridge_session_uuid,
        source,
        "backend.rfid_scan.unregistered",
        "El lector RFID detecto un tag no registrado.",
        {"uid_normalized": input_value, "register_path": register_path},
        "warning",
        bridge_metadata,
    )
    return JsonResponse(
        {
            "ok": False,
            "status": "unregistered",
            "message": "Tag no registrado.",
            "register_url": request.build_absolute_uri(register_path),
            "register_path": register_path,
            "nfc_id": input_value,
        },
        status=404,
    )


@require_GET
def ping_rfid(request: HttpRequest) -> JsonResponse:
    return JsonResponse(
        {
            "ok": True,
            "message": "RFID endpoint reachable",
            "time": timezone.now().strftime("%Y-%m-%d %H:%M:%S"),
        }
    )


@require_GET
def register_tag(request: HttpRequest, nfc_id: str) -> HttpResponse:
    normalized_nfc = normalize_nfc_id(nfc_id)
    student_form = StudentRegistrationForm(initial={"nfc_id": normalized_nfc})
    camera_form = CameraRegistrationForm(initial={"nfc_id": normalized_nfc})
    return render(
        request,
        "inventory/register.html",
        {"nfc_id": normalized_nfc, "student_form": student_form, "camera_form": camera_form},
    )


@require_POST
def store_student(request: HttpRequest) -> HttpResponse:
    form = StudentRegistrationForm(request.POST)
    if not form.is_valid():
        camera_form = CameraRegistrationForm(initial={"nfc_id": normalize_nfc_id(request.POST.get("nfc_id"))})
        return render(
            request,
            "inventory/register.html",
            {
                "nfc_id": normalize_nfc_id(request.POST.get("nfc_id")),
                "student_form": form,
                "camera_form": camera_form,
            },
            status=422,
        )

    estudiante = get_object_or_404(Estudiante, id=form.cleaned_data["estudiante"].id)
    estudiante.nfc_id = form.cleaned_data["nfc_id"]
    estudiante.alias = form.cleaned_data["alias"]
    estudiante.save(update_fields=["nfc_id", "alias", "updated_at"])
    set_current_student(request, estudiante)
    messages.success(request, f"Tag asignado a: {estudiante.nombre}. Escanea una camara.")
    return redirect("inventory:index")


@require_POST
def store_camera(request: HttpRequest) -> HttpResponse:
    form = CameraRegistrationForm(request.POST)
    if not form.is_valid():
        student_form = StudentRegistrationForm(initial={"nfc_id": normalize_nfc_id(request.POST.get("nfc_id"))})
        return render(
            request,
            "inventory/register.html",
            {
                "nfc_id": normalize_nfc_id(request.POST.get("nfc_id")),
                "student_form": student_form,
                "camera_form": form,
            },
            status=422,
        )

    camara = get_object_or_404(Camara, id=form.cleaned_data["camara"].id)
    camara.nfc_id = form.cleaned_data["nfc_id"]
    camara.alias = form.cleaned_data["alias"]
    camara.save(update_fields=["nfc_id", "alias", "updated_at"])
    messages.success(request, f"Tag asignado a camara: {camara.modelo}")
    return redirect("inventory:index")


@require_GET
def history_view(request: HttpRequest) -> HttpResponse:
    logs = LogPrestamo.objects.select_related("estudiante", "camara").all()
    return render(request, "inventory/history.html", {"logs": logs})


@csrf_exempt
@require_POST
def telemetry_collect(request: HttpRequest) -> JsonResponse:
    payload = _json_body(request)
    session_data = payload.get("session") if isinstance(payload.get("session"), dict) else {}
    events = payload.get("events") if isinstance(payload.get("events"), list) else []

    session_uuid = str(session_data.get("session_uuid", "")).strip()
    if not session_uuid:
        return JsonResponse({"ok": False, "message": "session_uuid es obligatorio."}, status=422)

    stored_session = telemetry_recorder.touch_session(
        {
            "session_uuid": session_uuid,
            "session_type": str(session_data.get("session_type", "web")).strip() or "web",
            "source": session_data.get("source", "inventory-web"),
            "page_name": session_data.get("page_name", "inventory"),
            "page_path": session_data.get("page_path", request.path),
            "page_url": session_data.get("page_url", request.build_absolute_uri()),
            "status": session_data.get("status", "active"),
            "timeout_seconds": session_data.get("timeout_seconds", 60),
            "user_agent": session_data.get("user_agent", request.META.get("HTTP_USER_AGENT")),
            "metadata": session_data.get("metadata", {}),
            "started_at": session_data.get("started_at"),
            "last_seen_at": session_data.get("last_seen_at", timezone.now().isoformat()),
            "ended_at": session_data.get("ended_at"),
        }
    )

    stored_events = []
    for event in events:
        if not isinstance(event, dict):
            continue
        stored_events.append(
            telemetry_recorder.record_event(
                {
                    "event_uuid": event.get("event_uuid"),
                    "session_uuid": stored_session.session_uuid if stored_session else session_uuid,
                    "session_type": stored_session.session_type if stored_session else "web",
                    "channel": event.get("channel", "web_ui"),
                    "event_type": event.get("event_type", "web.event"),
                    "level": event.get("level", "info"),
                    "source": event.get("source", stored_session.source if stored_session else "inventory-web"),
                    "uid": event.get("uid"),
                    "message": event.get("message"),
                    "payload": event.get("payload", {}),
                    "occurred_at": event.get("occurred_at", timezone.now().isoformat()),
                    "page_name": stored_session.page_name if stored_session else None,
                    "page_path": stored_session.page_path if stored_session else None,
                    "page_url": stored_session.page_url if stored_session else None,
                    "user_agent": stored_session.user_agent if stored_session else None,
                    "timeout_seconds": stored_session.timeout_seconds if stored_session else 60,
                }
            ).event_uuid
        )

    return JsonResponse(
        {
            "ok": True,
            "session_uuid": stored_session.session_uuid if stored_session else session_uuid,
            "stored_events": stored_events,
            "server_time": timezone.now().isoformat(),
        }
    )


@require_GET
def telemetry_snapshot(request: HttpRequest) -> JsonResponse:
    session_uuid = request.GET.get("session_uuid", "")
    limit = int(request.GET.get("limit", 20))
    return JsonResponse(telemetry_recorder.snapshot(session_uuid, limit))


def _handle_camera_transition(camara: Camara, estudiante: Estudiante):
    with transaction.atomic():
        if camara.estado == Camara.Estado.DISPONIBLE:
            camara.estado = Camara.Estado.PRESTADA
            camara.save(update_fields=["estado", "updated_at"])
            LogPrestamo.objects.create(estudiante=estudiante, camara=camara, accion=LogPrestamo.Accion.PRESTAMO)
            return ("backend.web_scan.loan_ok", f"Prestamo exitoso: {camara.modelo}.")

        if camara.estado == Camara.Estado.PRESTADA:
            camara.estado = Camara.Estado.DISPONIBLE
            camara.save(update_fields=["estado", "updated_at"])
            LogPrestamo.objects.create(estudiante=estudiante, camara=camara, accion=LogPrestamo.Accion.DEVOLUCION)
            return ("backend.web_scan.return_ok", f"Devolucion exitosa: {camara.modelo}.")

    return None


def _record_web_event(context, event_type, message, payload=None, level="info") -> None:
    session_uuid = str(context.get("telemetry_session_uuid", "")).strip()
    if not session_uuid:
        return
    telemetry_recorder.record_event(
        {
            "session_uuid": session_uuid,
            "session_type": "web",
            "channel": "backend",
            "event_type": event_type,
            "level": level,
            "source": context.get("source", "inventory-web"),
            "message": message,
            "payload": {
                "flow": context.get("flow", "web_scan"),
                "uid_raw": context.get("uid_raw"),
                "uid_normalized": context.get("input"),
                **(payload or {}),
            },
        }
    )


def _record_bridge_event(session_uuid, source, event_type, message, payload=None, level="info", metadata=None) -> None:
    data = {
        "session_type": "bridge",
        "channel": "backend",
        "event_type": event_type,
        "level": level,
        "source": source,
        "message": message,
        "payload": payload or {},
        "metadata": metadata or {},
    }
    if session_uuid:
        data["session_uuid"] = session_uuid
    telemetry_recorder.record_event(data)


def _json_body(request: HttpRequest) -> dict:
    if not request.body:
        return {}
    try:
        payload = json.loads(request.body.decode("utf-8"))
    except json.JSONDecodeError:
        return {}
    return payload if isinstance(payload, dict) else {}
