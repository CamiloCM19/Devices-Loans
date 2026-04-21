# Django Port

Esta carpeta contiene la migracion funcional de la app hacia `Python + Django + HTML + TypeScript`, manteniendo las rutas principales del sistema actual:

- `GET /inventory`
- `POST /inventory/scan`
- `POST /inventory/scan/rfid`
- `GET /inventory/scan/rfid/ping`
- `POST /inventory/telemetry/collect`
- `GET /inventory/telemetry/snapshot`
- `GET /inventory/register/<nfc_id>`
- `POST /inventory/register/student`
- `POST /inventory/register/camera`
- `GET /historial`

## Arranque rapido

```powershell
cd C:\xampp\htdocs\control-camaras
.\.venv\Scripts\python.exe django_port\manage.py migrate
.\.venv\Scripts\python.exe django_port\manage.py runserver 0.0.0.0:8001
```

Si no quieres usar MySQL local para validar la migracion:

```powershell
$env:DJANGO_USE_SQLITE = "1"
.\.venv\Scripts\python.exe django_port\manage.py migrate
.\.venv\Scripts\python.exe django_port\manage.py runserver 0.0.0.0:8001
```

## TypeScript

Los fuentes TypeScript viven en `django_port/static_src/` y se compilan a `django_port/static/`.

```powershell
npm run build:django-ts
```

## Notas

- La migracion se hizo en paralelo para no tocar el flujo Laravel existente.
- Los modelos Django usan los mismos nombres de tabla que la app actual para facilitar una transicion gradual.
- El bridge `scripts/nfc_bridge.py` puede apuntar al backend Django cambiando `RFID_BRIDGE_API_URL`.
