from django.contrib import admin

from .models import Camara, Estudiante, HardwareTelemetryEvent, HardwareTelemetrySession, LogPrestamo


@admin.register(Estudiante)
class EstudianteAdmin(admin.ModelAdmin):
    list_display = ("id", "nombre", "nfc_id", "alias", "activo")
    search_fields = ("nombre", "nfc_id", "alias")


@admin.register(Camara)
class CamaraAdmin(admin.ModelAdmin):
    list_display = ("id", "modelo", "nfc_id", "alias", "estado")
    search_fields = ("modelo", "nfc_id", "alias")
    list_filter = ("estado",)


@admin.register(LogPrestamo)
class LogPrestamoAdmin(admin.ModelAdmin):
    list_display = ("id", "estudiante", "camara", "accion", "created_at")
    list_filter = ("accion",)
    search_fields = ("estudiante__nombre", "camara__modelo")


@admin.register(HardwareTelemetrySession)
class HardwareTelemetrySessionAdmin(admin.ModelAdmin):
    list_display = ("session_uuid", "session_type", "source", "status", "last_seen_at")
    search_fields = ("session_uuid", "source", "page_name")
    list_filter = ("session_type", "status")


@admin.register(HardwareTelemetryEvent)
class HardwareTelemetryEventAdmin(admin.ModelAdmin):
    list_display = ("event_uuid", "event_type", "session_type", "source", "occurred_at")
    search_fields = ("event_uuid", "event_type", "source", "uid")
    list_filter = ("session_type", "channel", "level")
