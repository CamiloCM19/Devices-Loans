from __future__ import annotations

from django.db import models


class TimestampedModel(models.Model):
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        abstract = True


class Estudiante(TimestampedModel):
    class Meta:
        db_table = "estudiantes"
        ordering = ("nombre",)

    nfc_id = models.CharField(max_length=255, unique=True, null=True, blank=True)
    nombre = models.CharField(max_length=255)
    alias = models.CharField(max_length=255, null=True, blank=True)
    activo = models.BooleanField(default=True)

    def __str__(self) -> str:
        return self.nombre


class Camara(TimestampedModel):
    class Estado(models.TextChoices):
        DISPONIBLE = "Disponible", "Disponible"
        PRESTADA = "Prestada", "Prestada"
        MANTENIMIENTO = "Mantenimiento", "Mantenimiento"

    class Meta:
        db_table = "camaras"
        ordering = ("modelo",)

    nfc_id = models.CharField(max_length=255, unique=True, null=True, blank=True)
    modelo = models.CharField(max_length=255)
    alias = models.CharField(max_length=255, null=True, blank=True)
    estado = models.CharField(max_length=32, choices=Estado.choices, default=Estado.DISPONIBLE)

    def __str__(self) -> str:
        return self.modelo

    @property
    def display_model(self) -> str:
        return self.modelo.replace("Canon T7", "Camara")


class LogPrestamo(TimestampedModel):
    class Accion(models.TextChoices):
        PRESTAMO = "Prestamo", "Prestamo"
        DEVOLUCION = "Devolucion", "Devolucion"

    class Meta:
        db_table = "logs_prestamos"
        ordering = ("-created_at",)

    estudiante = models.ForeignKey(Estudiante, on_delete=models.CASCADE, related_name="logs")
    camara = models.ForeignKey(Camara, on_delete=models.CASCADE, related_name="logs")
    accion = models.CharField(max_length=32, choices=Accion.choices)
    observacion = models.TextField(null=True, blank=True)


class HardwareTelemetrySession(TimestampedModel):
    class Meta:
        db_table = "hardware_telemetry_sessions"
        ordering = ("-last_seen_at", "-id")

    session_uuid = models.CharField(max_length=255, unique=True)
    session_type = models.CharField(max_length=32)
    source = models.CharField(max_length=255, null=True, blank=True, db_index=True)
    page_name = models.CharField(max_length=255, null=True, blank=True)
    page_path = models.CharField(max_length=255, null=True, blank=True)
    page_url = models.CharField(max_length=255, null=True, blank=True)
    status = models.CharField(max_length=32, default="active")
    timeout_seconds = models.PositiveIntegerField(default=60)
    user_agent = models.TextField(null=True, blank=True)
    metadata = models.JSONField(null=True, blank=True)
    started_at = models.DateTimeField(null=True, blank=True, db_index=True)
    last_seen_at = models.DateTimeField(null=True, blank=True, db_index=True)
    ended_at = models.DateTimeField(null=True, blank=True, db_index=True)

    def __str__(self) -> str:
        return self.session_uuid


class HardwareTelemetryEvent(TimestampedModel):
    class Meta:
        db_table = "hardware_telemetry_events"
        ordering = ("-occurred_at", "-id")

    hardware_telemetry_session = models.ForeignKey(
        HardwareTelemetrySession,
        null=True,
        blank=True,
        on_delete=models.SET_NULL,
        related_name="events",
    )
    event_uuid = models.CharField(max_length=255, unique=True)
    session_uuid = models.CharField(max_length=255, null=True, blank=True, db_index=True)
    session_type = models.CharField(max_length=32, null=True, blank=True, db_index=True)
    channel = models.CharField(max_length=32, db_index=True)
    event_type = models.CharField(max_length=120, db_index=True)
    level = models.CharField(max_length=16, default="info", db_index=True)
    source = models.CharField(max_length=255, null=True, blank=True, db_index=True)
    uid = models.CharField(max_length=255, null=True, blank=True, db_index=True)
    correlation_id = models.CharField(max_length=255, null=True, blank=True, db_index=True)
    message = models.TextField(null=True, blank=True)
    payload = models.JSONField(null=True, blank=True)
    occurred_at = models.DateTimeField(null=True, blank=True, db_index=True)
