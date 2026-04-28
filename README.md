laravel: http://127.0.0.1:8000

## Instalar en un dispositivo nuevo

En Raspberry Pi, Debian o Ubuntu, clona el repositorio y ejecuta:

```bash
bash scripts/install_new_device.sh
```

El script instala paquetes del sistema cuando hay `apt-get`, prepara `.env`,
instala dependencias de Composer y npm, crea la base SQLite, ejecuta migraciones
y compila assets. Tambien instala las dependencias del port Django por defecto.

Variables utiles:

```bash
APP_PORT=8000 RFID_READER_DRIVER=mfrc522 bash scripts/install_new_device.sh
INSTALL_DJANGO_PORT=0 bash scripts/install_new_device.sh
RUN_TESTS=1 bash scripts/install_new_device.sh
```

Para correr despues de instalar:

```bash
APP_HOST=0.0.0.0 APP_PORT=8000 ./scripts/start_server_pi.sh
RFID_READER_DRIVER=mfrc522 ./scripts/start_rfid_listener.sh
```

## Lector MFRC522 en un dispositivo remoto

Si el lector MFRC522 esta conectado por USB/serial a otro equipo de la red, ese
equipo tambien puede leer tags y reenviarlos al servidor central.

En el dispositivo remoto configura:

```bash
RFID_REMOTE_API_URL=http://IP-DEL-SERVIDOR:8000
RFID_REMOTE_API_TOKEN=
RFID_READER_DRIVER=mfrc522
RFID_SERIAL_DEVICE=auto
```

Luego inicia el listener remoto:

```bash
./scripts/start_rfid_listener.sh
```

En Windows:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/start_rfid_listener.ps1
```

El listener acepta tanto la base del servidor como la URL completa del endpoint
`/inventory/scan/rfid`. Cada UID leido por el equipo remoto se envia al servidor
central para actualizar inventario, telemetria y flujo de prestamo/devolucion.

## Wemos D1 mini + RFID RC522 por WiFi

Tambien puedes usar un Wemos D1 mini como lector WiFi directo, sin PC intermedio.
Cuando el Wemos enciende, se conecta a la red, conserva una IP propia y queda
leyendo tags en automatico. Cada tag detectado se envia por HTTP al backend
Laravel, y el backend actualiza la base de datos con el flujo existente de
estudiante/camara, prestamo/devolucion o registro de tag nuevo.

El ejemplo esta en:

```text
scripts/wemos_d1_mini_mfrc522_bridge.ino.example
```

Configura en el sketch:

```cpp
const char* WIFI_SSID = "TU_WIFI";
const char* WIFI_PASS = "TU_PASS";
IPAddress LOCAL_IP(192, 168, 1, 60);
const char* API_URL = "http://IP-DEL-SERVIDOR:8000/inventory/scan/rfid";
const char* SIGNING_KEY = "LA_MISMA_CLAVE_DEL_ENV";
```

Y en el `.env` del servidor Laravel configura la misma clave:

```env
RFID_READER_SIGNING_KEY=LA_MISMA_CLAVE_DEL_ENV
```

Cada lectura genera una firma distinta con `uid + source + nonce`. Si alguien
manda datos sin esa firma, con una firma falsa o repite un `nonce` ya usado, el
backend responde `401` y no procesa la lectura.

Cableado recomendado:

```text
RC522 3.3V -> Wemos 3V3
RC522 GND  -> Wemos G
RC522 RST  -> Wemos D3
RC522 SDA  -> Wemos D8
RC522 SCK  -> Wemos D5
RC522 MOSI -> Wemos D7
RC522 MISO -> Wemos D6
```

El RC522 trabaja a 3.3V. No lo alimentes con 5V. Instala en Arduino IDE las
librerias `MFRC522` y `ESP8266WiFi`, selecciona la placa `LOLIN(WEMOS) D1 R2 &
mini`, sube el sketch y abre el monitor serial a `115200`.

Para probar el servidor desde el navegador:

```text
http://IP-DEL-SERVIDOR:8000/inventory/scan/rfid/ping
```

Despues de subir el sketch no necesitas abrir la pagina web para que el lector
trabaje. Basta con que el servidor Laravel este encendido en la red y que el
Wemos pueda llegar a `API_URL`.

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

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
