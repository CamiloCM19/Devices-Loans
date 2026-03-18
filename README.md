<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Repositorio Remoto

Este proyecto esta vinculado al repositorio de GitHub:

- `origin`: `https://github.com/CamiloCM19/Devices-Loans.git`

Para verificar la configuracion local:

```powershell
git remote -v
```

## Hosting En Raspberry Pi

IP LAN actual del Pi:

- `10.204.248.185`

Objetivo:

- El Pi hostea Laravel
- Cualquier laptop o dispositivo conectado al mismo hotspot abre la interfaz en el navegador
- El ESP8266 envia lecturas al mismo Pi por HTTP

Flujo recomendado en el Raspberry Pi:

```bash
git clone https://github.com/CamiloCM19/Devices-Loans.git
cd Devices-Loans
chmod +x scripts/deploy_pi.sh scripts/start_server_pi.sh
APP_URL=http://10.204.248.185:8000 ./scripts/deploy_pi.sh
APP_HOST=0.0.0.0 APP_PORT=8000 ./scripts/start_server_pi.sh
```

Despues de eso, la interfaz debe abrir desde cualquier equipo en la misma red en:

```text
http://10.204.248.185:8000
```

Notas de conexion:

- `APP_URL` en el Pi debe apuntar a `http://10.204.248.185:8000`
- El servidor ya arranca escuchando en `0.0.0.0`, no solo en `localhost`
- `scripts/deploy_pi.sh` fuerza SQLite local para que el Pi no dependa de MySQL
- Para cambios nuevos desde GitHub en el Pi:

```bash
git pull origin main
APP_URL=http://10.204.248.185:8000 ./scripts/deploy_pi.sh
```

## Control de RFID (Windows)

Para iniciar servidor + frontend (modo ESP8266/RC522 por HTTP):

```powershell
composer run dev:win
```

Si usas lector serial por COM/USB (no ESP), usa:

```powershell
composer run dev:win:serial
```

Modo recomendado (compatible Pi USB y remoto USB):

- Ejecutar el bridge en modo `api` para enviar UID por HTTP al backend.
- Evita dependencia de foco/teclado y funciona en Linux headless (Pi) y Windows.

Bridge USB en Raspberry Pi (lector conectado al Pi):

```bash
cd ~/Devices-Loans
python3 -m venv .venv
./.venv/bin/python -m pip install pyserial
chmod +x scripts/start_nfc_bridge.sh
RFID_BRIDGE_API_URL=http://127.0.0.1:8000/inventory/scan/esp \
RFID_BRIDGE_SOURCE=pi-usb-1 \
./scripts/start_nfc_bridge.sh
```

Bridge USB en Windows remoto (lector conectado al PC que abre la web):

```powershell
py -3 -m venv .venv
.\.venv\Scripts\python.exe -m pip install pyserial
$env:RFID_BRIDGE_API_URL = "http://10.204.248.185:8000/inventory/scan/esp"
$env:RFID_BRIDGE_SOURCE = "remote-usb-1"
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/start_nfc_bridge.ps1
```

Opcional (modo legacy por teclado):

```powershell
.\.venv\Scripts\python.exe -m pip install pyautogui
$env:RFID_BRIDGE_MODE = "keyboard"
```

Endpoint para ESP8266 / ESP32:

- `POST /inventory/scan/esp`
- body JSON o form-data con: `uid` (o `nfc_id`), `source` opcional, `token` opcional
- prueba conectividad: `GET /inventory/scan/esp/ping`
- sketch de prueba hardware/red (ESP8266 + RC522): `scripts/esp8266_rc522_test.ino`
- sketch de prueba hardware/red (ESP32 + PN532): `scripts/esp32_pn532_test.ino`

PN532 + ESP32 (drivers/librerias):

- Arduino IDE -> Board Manager: instala `esp32 by Espressif Systems`
- Arduino IDE -> Library Manager: instala `Adafruit PN532`
- Si el puerto COM no aparece en Windows, instala el driver USB segun tu placa ESP32:
- CP210x (Silicon Labs) o CH340/CH341 (WCH)

Arduino CLI (opcional):

```bash
arduino-cli core update-index
arduino-cli core install esp32:esp32
arduino-cli lib install "Adafruit PN532"
```

Importante de red:

- Desde el ESP no uses `127.0.0.1` ni `localhost`.
- Usa la IP LAN del Raspberry Pi.
- IP actual del Pi: `10.204.248.185`
- URL ejemplo: `http://10.204.248.185:8000/inventory/scan/esp`

Ejemplo (desde ESP o prueba local):

```bash
curl -X POST http://10.204.248.185:8000/inventory/scan/esp ^
  -H "Content-Type: application/json" ^
  -d "{\"uid\":\"04A1B2C3\",\"source\":\"esp-lab-1\",\"token\":\"TU_TOKEN\"}"
```

Opcional: define `RFID_ESP_TOKEN` en `.env` para proteger el endpoint.

Si el endpoint responde con un tag no registrado, ahora devuelve:

- `register_url`: URL absoluta basada en el host con el que entro la solicitud
- `register_path`: ruta relativa usable aunque cambie el host/IP actual

Notas:
- En modo automatico, el bridge ignora puertos Bluetooth (BTHENUM) por defecto.
- Si necesitas probar Bluetooth serial manualmente, usa `--include-bluetooth`.
- Si tu lector funciona como teclado (keyboard wedge), no requiere puerto COM.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
