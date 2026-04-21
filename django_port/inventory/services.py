from __future__ import annotations

import json
import uuid
from datetime import timedelta
from pathlib import Path
from typing import Any

from django.conf import settings
from django.db.models import Q, QuerySet, Value
from django.db.models.functions import Replace, Upper
from django.utils import timezone
from django.utils.dateparse import parse_datetime

from .models import Camara, Estudiante, HardwareTelemetryEvent, HardwareTelemetrySession


def normalize_nfc_id(value: str | None) -> str:
    cleaned = (value or "").strip().upper()
    return cleaned.replace(" ", "").replace("-", "")


def parse_timestamp(value: Any):
    if not value:
        return None
    if hasattr(value, "tzinfo"):
        return value
    parsed = parse_datetime(str(value))
    if parsed is None:
        return None
    if timezone.is_naive(parsed):
        return timezone.make_aware(parsed, timezone.get_current_timezone())
    return parsed


def normalized_nfc_queryset(queryset: QuerySet):
    return queryset.annotate(
        normalized_nfc=Replace(
            Replace(Upper("nfc_id"), Value(" "), Value("")),
            Value("-"),
            Value(""),
        )
    )


def find_estudiante_by_nfc(nfc_id: str) -> Estudiante | None:
    if not nfc_id:
        return None
    return normalized_nfc_queryset(Estudiante.objects.all()).filter(normalized_nfc=nfc_id).first()


def find_camara_by_nfc(nfc_id: str) -> Camara | None:
    if not nfc_id:
        return None
    return normalized_nfc_queryset(Camara.objects.all()).filter(normalized_nfc=nfc_id).first()


def tag_exists(nfc_id: str) -> bool:
    if not nfc_id:
        return False
    return find_estudiante_by_nfc(nfc_id) is not None or find_camara_by_nfc(nfc_id) is not None


def _nullable_str(value: Any) -> str | None:
    text = str(value or "").strip()
    return text or None


class TelemetryRecorder:
    def touch_session(self, data: dict[str, Any]) -> HardwareTelemetrySession | None:
        session_uuid = (str(data.get("session_uuid", ""))).strip()
        if not session_uuid:
            return None

        session, _ = HardwareTelemetrySession.objects.get_or_create(session_uuid=session_uuid)
        metadata = dict(session.metadata or {})
        incoming_metadata = data.get("metadata") if isinstance(data.get("metadata"), dict) else {}
        if incoming_metadata:
            metadata.update(incoming_metadata)

        started_at = parse_timestamp(data.get("started_at")) or session.started_at or timezone.now()
        last_seen_at = parse_timestamp(data.get("last_seen_at")) or timezone.now()
        status = (str(data.get("status", session.status or "active"))).strip() or "active"

        if "ended_at" in data:
            ended_at = parse_timestamp(data.get("ended_at"))
        elif status == "active":
            ended_at = None
        else:
            ended_at = session.ended_at

        session.session_type = (str(data.get("session_type", session.session_type or "web"))).strip() or "web"
        session.source = _nullable_str(data.get("source", session.source))
        session.page_name = _nullable_str(data.get("page_name", session.page_name))
        session.page_path = _nullable_str(data.get("page_path", session.page_path))
        session.page_url = _nullable_str(data.get("page_url", session.page_url))
        session.status = "closed" if ended_at is not None else status
        session.timeout_seconds = max(1, int(data.get("timeout_seconds", session.timeout_seconds or 60)))
        session.user_agent = _nullable_str(data.get("user_agent", session.user_agent))
        session.metadata = metadata or None
        session.started_at = started_at
        session.last_seen_at = last_seen_at
        session.ended_at = ended_at
        session.save()
        return session

    def record_event(self, data: dict[str, Any]) -> HardwareTelemetryEvent:
        session = None
        session_uuid = (str(data.get("session_uuid", ""))).strip()
        if session_uuid:
            session = self.touch_session(
                {
                    "session_uuid": session_uuid,
                    "session_type": data.get("session_type", "web"),
                    "source": data.get("source"),
                    "page_name": data.get("page_name"),
                    "page_path": data.get("page_path"),
                    "page_url": data.get("page_url"),
                    "status": data.get("status", "active"),
                    "timeout_seconds": data.get("timeout_seconds", 60),
                    "user_agent": data.get("user_agent"),
                    "metadata": data.get("metadata", {}),
                    "started_at": data.get("session_started_at"),
                    "last_seen_at": data.get("occurred_at") or data.get("last_seen_at"),
                    "ended_at": data.get("session_ended_at"),
                }
            )

        payload = data.get("payload") if isinstance(data.get("payload"), dict) else {}
        uid = _nullable_str(data.get("uid") or payload.get("uid_normalized") or payload.get("uid"))
        event_uuid = (str(data.get("event_uuid", ""))).strip() or str(uuid.uuid4())

        event, _ = HardwareTelemetryEvent.objects.get_or_create(event_uuid=event_uuid)
        event.hardware_telemetry_session = session
        event.session_uuid = session.session_uuid if session else _nullable_str(session_uuid)
        event.session_type = session.session_type if session else _nullable_str(data.get("session_type"))
        event.channel = (str(data.get("channel", "backend"))).strip() or "backend"
        event.event_type = (str(data.get("event_type", "telemetry.event"))).strip() or "telemetry.event"
        event.level = (str(data.get("level", "info"))).strip() or "info"
        event.source = _nullable_str(data.get("source") or (session.source if session else None))
        event.uid = uid
        event.correlation_id = _nullable_str(data.get("correlation_id"))
        event.message = _nullable_str(data.get("message"))
        event.payload = payload or None
        event.occurred_at = parse_timestamp(data.get("occurred_at")) or timezone.now()
        event.save()
        return event

    def snapshot(self, browser_session_uuid: str | None = None, recent_limit: int = 20) -> dict[str, Any]:
        browser_session_uuid = _nullable_str(browser_session_uuid)
        browser_session = (
            HardwareTelemetrySession.objects.filter(session_uuid=browser_session_uuid).first()
            if browser_session_uuid
            else None
        )

        recent_events = [
            self.format_event(event)
            for event in HardwareTelemetryEvent.objects.all()[: max(1, min(recent_limit, 50))]
        ]

        latest_reader_event = (
            HardwareTelemetryEvent.objects.filter(
                Q(session_type="bridge")
                | Q(event_type__startswith="backend.rfid_scan.")
                | Q(event_type__startswith="bridge.")
            ).first()
        )

        latest_browser_event = (
            HardwareTelemetryEvent.objects.filter(session_uuid=browser_session_uuid).first()
            if browser_session_uuid
            else None
        )

        cutoff = timezone.now() - timedelta(minutes=20)
        active_bridge_sessions = []
        for session in HardwareTelemetrySession.objects.filter(
            session_type="bridge",
            last_seen_at__gte=cutoff,
        )[:5]:
            active_bridge_sessions.append(
                {
                    "session_uuid": session.session_uuid,
                    "source": session.source,
                    "status": session.status,
                    "started_at": session.started_at.isoformat() if session.started_at else None,
                    "last_seen_at": session.last_seen_at.isoformat() if session.last_seen_at else None,
                    "active_student": self._extract_active_student(session),
                }
            )

        return {
            "browser_session": self._format_session(browser_session),
            "latest_reader_event": self.format_event(latest_reader_event) if latest_reader_event else None,
            "latest_browser_event": self.format_event(latest_browser_event) if latest_browser_event else None,
            "active_bridge_sessions": active_bridge_sessions,
            "recent_events": recent_events,
            "bridge_log_events": self.read_bridge_log_tail(15),
            "server_time": timezone.now().isoformat(),
        }

    def read_bridge_log_tail(self, max_lines: int = 20) -> list[dict[str, Any]]:
        path = Path(settings.RFID_BRIDGE_TELEMETRY_FILE)
        if not path.exists():
            return []

        lines = [line for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
        parsed: list[dict[str, Any]] = []
        for line in lines[-max(1, min(max_lines, 100)) :]:
            try:
                event = json.loads(line)
            except json.JSONDecodeError:
                continue
            if isinstance(event, dict):
                parsed.append(event)
        return parsed

    def format_event(self, event: HardwareTelemetryEvent) -> dict[str, Any]:
        return {
            "id": event.id,
            "event_uuid": event.event_uuid,
            "session_uuid": event.session_uuid,
            "session_type": event.session_type,
            "channel": event.channel,
            "event_type": event.event_type,
            "level": event.level,
            "source": event.source,
            "uid": event.uid,
            "message": event.message,
            "payload": event.payload or {},
            "occurred_at": event.occurred_at.isoformat() if event.occurred_at else None,
        }

    def _format_session(self, session: HardwareTelemetrySession | None) -> dict[str, Any] | None:
        if session is None:
            return None
        return {
            "session_uuid": session.session_uuid,
            "session_type": session.session_type,
            "source": session.source,
            "page_name": session.page_name,
            "page_path": session.page_path,
            "status": session.status,
            "started_at": session.started_at.isoformat() if session.started_at else None,
            "last_seen_at": session.last_seen_at.isoformat() if session.last_seen_at else None,
            "ended_at": session.ended_at.isoformat() if session.ended_at else None,
        }

    def _extract_active_student(self, session: HardwareTelemetrySession) -> dict[str, Any] | None:
        metadata = session.metadata or {}
        active_student = metadata.get("active_student")
        if not isinstance(active_student, dict):
            return None
        expires_at = parse_timestamp(active_student.get("expires_at"))
        if expires_at and expires_at < timezone.now():
            return None
        student_id = active_student.get("id")
        if not student_id:
            return None
        student = Estudiante.objects.filter(id=student_id).first()
        if student is None:
            return None
        return {"id": student.id, "nombre": student.nombre}


telemetry_recorder = TelemetryRecorder()


def get_current_student(request) -> Estudiante | None:
    student_id = request.session.get("estudiante_actual_id")
    if not student_id:
        return None
    return Estudiante.objects.filter(id=student_id).first()


def set_current_student(request, estudiante: Estudiante) -> None:
    request.session["estudiante_actual_id"] = estudiante.id


def clear_current_student(request) -> None:
    request.session.pop("estudiante_actual_id", None)


def ensure_bridge_session(session_uuid: str, source: str, metadata: dict[str, Any] | None = None):
    if not session_uuid.strip():
        return None
    return telemetry_recorder.touch_session(
        {
            "session_uuid": session_uuid,
            "session_type": "bridge",
            "source": source,
            "status": "active",
            "timeout_seconds": 60,
            "metadata": metadata or {},
            "last_seen_at": timezone.now().isoformat(),
        }
    )


def update_bridge_active_student(source: str, estudiante: Estudiante | None, bridge_session_uuid: str = "") -> None:
    session = None
    if bridge_session_uuid.strip():
        session = HardwareTelemetrySession.objects.filter(session_uuid=bridge_session_uuid).first()
    if session is None:
        session = HardwareTelemetrySession.objects.filter(session_type="bridge", source=source).first()
    if session is None:
        return

    metadata = dict(session.metadata or {})
    if estudiante is None:
        metadata.pop("active_student", None)
    else:
        metadata["active_student"] = {
            "id": estudiante.id,
            "nombre": estudiante.nombre,
            "expires_at": (timezone.now() + timedelta(minutes=20)).isoformat(),
        }
    session.metadata = metadata or None
    session.last_seen_at = timezone.now()
    session.status = "active"
    session.save(update_fields=["metadata", "last_seen_at", "status", "updated_at"])


def get_bridge_active_student(source: str) -> Estudiante | None:
    cutoff = timezone.now() - timedelta(minutes=20)
    session = HardwareTelemetrySession.objects.filter(
        session_type="bridge",
        source=source,
        last_seen_at__gte=cutoff,
    ).first()
    if session is None:
        return None
    metadata = session.metadata or {}
    active_student = metadata.get("active_student")
    if not isinstance(active_student, dict):
        return None
    expires_at = parse_timestamp(active_student.get("expires_at"))
    if expires_at and expires_at < timezone.now():
        update_bridge_active_student(source, None, session.session_uuid)
        return None
    student_id = active_student.get("id")
    if not student_id:
        return None
    return Estudiante.objects.filter(id=student_id).first()
