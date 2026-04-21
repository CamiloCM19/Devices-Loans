from django.urls import path

from . import views

app_name = "inventory"

urlpatterns = [
    path("", views.root_redirect, name="root"),
    path("inventory", views.inventory_index, name="index"),
    path("inventory/scan", views.process_inventory_scan, name="scan"),
    path("inventory/scan/rfid", views.process_inventory_scan_rfid, name="scan_rfid"),
    path("inventory/scan/rfid/ping", views.ping_rfid, name="scan_rfid_ping"),
    path("inventory/telemetry/collect", views.telemetry_collect, name="telemetry_collect"),
    path("inventory/telemetry/snapshot", views.telemetry_snapshot, name="telemetry_snapshot"),
    path("inventory/register/<str:nfc_id>", views.register_tag, name="register"),
    path("inventory/register/student", views.store_student, name="store_student"),
    path("inventory/register/camera", views.store_camera, name="store_camera"),
    path("historial", views.history_view, name="history"),
]
