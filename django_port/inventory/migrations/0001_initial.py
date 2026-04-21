from django.db import migrations, models
import django.db.models.deletion


class Migration(migrations.Migration):
    initial = True

    dependencies = []

    operations = [
        migrations.CreateModel(
            name="Camara",
            fields=[
                ("id", models.BigAutoField(auto_created=True, primary_key=True, serialize=False, verbose_name="ID")),
                ("created_at", models.DateTimeField(auto_now_add=True)),
                ("updated_at", models.DateTimeField(auto_now=True)),
                ("nfc_id", models.CharField(blank=True, max_length=255, null=True, unique=True)),
                ("modelo", models.CharField(max_length=255)),
                ("alias", models.CharField(blank=True, max_length=255, null=True)),
                (
                    "estado",
                    models.CharField(
                        choices=[
                            ("Disponible", "Disponible"),
                            ("Prestada", "Prestada"),
                            ("Mantenimiento", "Mantenimiento"),
                        ],
                        default="Disponible",
                        max_length=32,
                    ),
                ),
            ],
            options={"db_table": "camaras", "ordering": ("modelo",)},
        ),
        migrations.CreateModel(
            name="Estudiante",
            fields=[
                ("id", models.BigAutoField(auto_created=True, primary_key=True, serialize=False, verbose_name="ID")),
                ("created_at", models.DateTimeField(auto_now_add=True)),
                ("updated_at", models.DateTimeField(auto_now=True)),
                ("nfc_id", models.CharField(blank=True, max_length=255, null=True, unique=True)),
                ("nombre", models.CharField(max_length=255)),
                ("alias", models.CharField(blank=True, max_length=255, null=True)),
                ("activo", models.BooleanField(default=True)),
            ],
            options={"db_table": "estudiantes", "ordering": ("nombre",)},
        ),
        migrations.CreateModel(
            name="HardwareTelemetrySession",
            fields=[
                ("id", models.BigAutoField(auto_created=True, primary_key=True, serialize=False, verbose_name="ID")),
                ("created_at", models.DateTimeField(auto_now_add=True)),
                ("updated_at", models.DateTimeField(auto_now=True)),
                ("session_uuid", models.CharField(max_length=255, unique=True)),
                ("session_type", models.CharField(max_length=32)),
                ("source", models.CharField(blank=True, db_index=True, max_length=255, null=True)),
                ("page_name", models.CharField(blank=True, max_length=255, null=True)),
                ("page_path", models.CharField(blank=True, max_length=255, null=True)),
                ("page_url", models.CharField(blank=True, max_length=255, null=True)),
                ("status", models.CharField(default="active", max_length=32)),
                ("timeout_seconds", models.PositiveIntegerField(default=60)),
                ("user_agent", models.TextField(blank=True, null=True)),
                ("metadata", models.JSONField(blank=True, null=True)),
                ("started_at", models.DateTimeField(blank=True, db_index=True, null=True)),
                ("last_seen_at", models.DateTimeField(blank=True, db_index=True, null=True)),
                ("ended_at", models.DateTimeField(blank=True, db_index=True, null=True)),
            ],
            options={"db_table": "hardware_telemetry_sessions", "ordering": ("-last_seen_at", "-id")},
        ),
        migrations.CreateModel(
            name="LogPrestamo",
            fields=[
                ("id", models.BigAutoField(auto_created=True, primary_key=True, serialize=False, verbose_name="ID")),
                ("created_at", models.DateTimeField(auto_now_add=True)),
                ("updated_at", models.DateTimeField(auto_now=True)),
                ("accion", models.CharField(choices=[("Prestamo", "Prestamo"), ("Devolucion", "Devolucion")], max_length=32)),
                ("observacion", models.TextField(blank=True, null=True)),
                ("camara", models.ForeignKey(on_delete=django.db.models.deletion.CASCADE, related_name="logs", to="inventory.camara")),
                ("estudiante", models.ForeignKey(on_delete=django.db.models.deletion.CASCADE, related_name="logs", to="inventory.estudiante")),
            ],
            options={"db_table": "logs_prestamos", "ordering": ("-created_at",)},
        ),
        migrations.CreateModel(
            name="HardwareTelemetryEvent",
            fields=[
                ("id", models.BigAutoField(auto_created=True, primary_key=True, serialize=False, verbose_name="ID")),
                ("created_at", models.DateTimeField(auto_now_add=True)),
                ("updated_at", models.DateTimeField(auto_now=True)),
                ("event_uuid", models.CharField(max_length=255, unique=True)),
                ("session_uuid", models.CharField(blank=True, db_index=True, max_length=255, null=True)),
                ("session_type", models.CharField(blank=True, db_index=True, max_length=32, null=True)),
                ("channel", models.CharField(db_index=True, max_length=32)),
                ("event_type", models.CharField(db_index=True, max_length=120)),
                ("level", models.CharField(db_index=True, default="info", max_length=16)),
                ("source", models.CharField(blank=True, db_index=True, max_length=255, null=True)),
                ("uid", models.CharField(blank=True, db_index=True, max_length=255, null=True)),
                ("correlation_id", models.CharField(blank=True, db_index=True, max_length=255, null=True)),
                ("message", models.TextField(blank=True, null=True)),
                ("payload", models.JSONField(blank=True, null=True)),
                ("occurred_at", models.DateTimeField(blank=True, db_index=True, null=True)),
                (
                    "hardware_telemetry_session",
                    models.ForeignKey(
                        blank=True,
                        null=True,
                        on_delete=django.db.models.deletion.SET_NULL,
                        related_name="events",
                        to="inventory.hardwaretelemetrysession",
                    ),
                ),
            ],
            options={"db_table": "hardware_telemetry_events", "ordering": ("-occurred_at", "-id")},
        ),
    ]
