<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Nuevo Tag - Control de Camaras</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="{{ asset('js/hardware-telemetry.js') }}"></script>
    @include('partials.unified-ui-head')
</head>

<body class="ui-body">

    <div class="ui-shell">

        <div class="ui-header-card mb-8">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="ui-kicker">Tag desconocido</p>
                    <h1 class="ui-title">Registrar nuevo tag RFID</h1>
                    <p class="ui-subtitle max-w-2xl">
                        El tag fue detectado pero todavia no pertenece a ningun estudiante ni a ninguna camara.
                        Puedes asignarlo a un registro pendiente o crear uno nuevo desde aqui.
                    </p>
                </div>
                <div class="ui-chip ui-mono px-5 py-4 text-center text-xl">
                    {{ $nfc_id }}
                </div>
            </div>
        </div>

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-4 text-rose-800 shadow-sm">
                <p class="font-semibold">No se pudo guardar el tag.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="ui-card mb-6">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-slate-700">Estado del lector</p>
                <span id="scanner_status_badge"
                    class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                    Esperando
                </span>
            </div>
            <p id="scanner_status_text" class="mt-2 text-sm text-slate-600">
                Si acercas otro tag, esta pagina debe detectarlo y redirigir al nuevo registro incluso cuando abras la web desde otro dispositivo en la misma red.
            </p>
            <p id="scanner_last_scan" class="mt-1 font-mono text-xs text-slate-500">
                Ultima lectura: sin datos.
            </p>
            <p id="telemetry_register_session" class="mt-1 font-mono text-xs text-slate-400">
                Sesion telemetrica: pendiente...
            </p>
            <p class="mt-1 text-xs text-slate-500">
                URL compartida: <span class="font-mono text-slate-700">{{ request()->getSchemeAndHttpHost() . route('inventory.index', [], false) }}</span>
            </p>
        </div>

        <div class="mb-6 grid gap-4 md:grid-cols-2">
            <div class="ui-stat-card ui-stat-card--primary">
                <p class="text-sm font-semibold text-blue-900">Estudiantes pendientes</p>
                <p class="mt-1 text-3xl font-bold text-blue-700">{{ $estudiantes->count() }}</p>
                <p class="mt-2 text-sm text-blue-800">Puedes enlazar este tag a un estudiante existente o crear uno nuevo al instante.</p>
            </div>
            <div class="ui-stat-card ui-stat-card--accent">
                <p class="text-sm font-semibold text-emerald-900">Camaras pendientes</p>
                <p class="mt-1 text-3xl font-bold text-emerald-700">{{ $camaras->count() }}</p>
                <p class="mt-2 text-sm text-emerald-800">Tambien puedes asociarlo a una camara ya creada o registrar una nueva sin salir de esta vista.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-8 xl:grid-cols-2">

            <section class="ui-card border border-blue-100">
                <div class="mb-6 flex items-center gap-3">
                    <div class="rounded-full bg-blue-100 p-3 text-blue-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">Asignar como estudiante</h2>
                        <p class="text-sm text-gray-500">Usa una de estas opciones para guardar el tag del carnet.</p>
                    </div>
                </div>

                <div class="grid gap-5 lg:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-semibold text-slate-800">1. Asignar a estudiante existente</p>
                        <p class="mt-1 text-xs text-slate-500">Solo aparecen estudiantes que todavia no tienen tag.</p>

                        @if ($estudiantes->isEmpty())
                            <div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-5 text-sm text-slate-500">
                                No hay estudiantes pendientes. Crea uno nuevo con el formulario de al lado.
                            </div>
                        @else
                            <form action="{{ route('inventory.storeStudent') }}" method="POST" class="mt-4 space-y-4">
                                @csrf
                                <input type="hidden" name="nfc_id" value="{{ $nfc_id }}">

                                <div>
                                    <label for="estudiante_id" class="mb-2 block text-sm font-medium text-slate-700">Estudiante</label>
                                    <select name="estudiante_id" id="estudiante_id"
                                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                        required>
                                        <option value="">Selecciona un estudiante</option>
                                        @foreach($estudiantes as $estudiante)
                                            <option value="{{ $estudiante->id }}" @selected(old('estudiante_id') == $estudiante->id)>
                                                {{ $estudiante->nombre }}{{ $estudiante->alias ? ' - ' . $estudiante->alias : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="alias_student" class="mb-2 block text-sm font-medium text-slate-700">Alias del tag</label>
                                    <input type="text" name="alias" id="alias_student" value="{{ old('alias') }}"
                                        class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                        placeholder="Ej. Monitor, Practicas, Grupo B">
                                </div>

                                <button type="submit"
                                    class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-700">
                                    Asignar tag a estudiante existente
                                </button>
                            </form>
                        @endif
                    </div>

                    <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4">
                        <p class="text-sm font-semibold text-blue-900">2. Crear estudiante nuevo</p>
                        <p class="mt-1 text-xs text-blue-700">Crea el registro y deja el tag asignado en un solo paso.</p>

                        <form action="{{ route('inventory.storeNewStudent') }}" method="POST" class="mt-4 space-y-4">
                            @csrf
                            <input type="hidden" name="nfc_id" value="{{ $nfc_id }}">

                            <div>
                                <label for="new_student_name" class="mb-2 block text-sm font-medium text-blue-900">Nombre completo</label>
                                <input type="text" name="nombre" id="new_student_name" value="{{ old('nombre') }}"
                                    class="w-full rounded-xl border border-blue-200 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="Ej. Laura Martinez" required>
                            </div>

                            <div>
                                <label for="new_student_alias" class="mb-2 block text-sm font-medium text-blue-900">Alias</label>
                                <input type="text" name="alias" id="new_student_alias" value="{{ old('alias') }}"
                                    class="w-full rounded-xl border border-blue-200 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="Opcional">
                            </div>

                                <button type="submit"
                                    class="w-full rounded-xl bg-amber-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-amber-700">
                                    Crear estudiante y asignar tag
                                </button>
                        </form>
                    </div>
                </div>
            </section>

            <section class="ui-card border border-emerald-100">
                <div class="mb-6 flex items-center gap-3">
                    <div class="rounded-full bg-emerald-100 p-3 text-emerald-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">Asignar como camara</h2>
                        <p class="text-sm text-gray-500">Asocia el tag a una camara existente o crea una nueva.</p>
                    </div>
                </div>

                <div class="grid gap-5 lg:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-semibold text-slate-800">1. Asignar a camara existente</p>
                        <p class="mt-1 text-xs text-slate-500">Aqui aparecen las camaras que todavia no tienen tag registrado.</p>

                        @if ($camaras->isEmpty())
                            <div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-5 text-sm text-slate-500">
                                No hay camaras pendientes. Crea una nueva con el formulario de al lado.
                            </div>
                        @else
                            <form action="{{ route('inventory.storeCamera') }}" method="POST" class="mt-4 space-y-4">
                                @csrf
                                <input type="hidden" name="nfc_id" value="{{ $nfc_id }}">

                                <div>
                                    <label for="camara_id" class="mb-2 block text-sm font-medium text-slate-700">Camara</label>
                                    <select name="camara_id" id="camara_id"
                                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                                        required>
                                        <option value="">Selecciona una camara</option>
                                        @foreach($camaras as $camara)
                                            <option value="{{ $camara->id }}" @selected(old('camara_id') == $camara->id)>
                                                {{ $camara->modelo }}{{ $camara->alias ? ' - ' . $camara->alias : '' }} - {{ $camara->estado }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="alias_camera" class="mb-2 block text-sm font-medium text-slate-700">Alias del tag</label>
                                    <input type="text" name="alias" id="alias_camera" value="{{ old('alias') }}"
                                        class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm text-slate-700 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                                        placeholder="Ej. Body 01, Cabina, Reserva">
                                </div>

                                <button type="submit"
                                    class="w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700">
                                    Asignar tag a camara existente
                                </button>
                            </form>
                        @endif
                    </div>

                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-sm font-semibold text-emerald-900">2. Crear camara nueva</p>
                        <p class="mt-1 text-xs text-emerald-700">Ideal cuando el equipo todavia no existe en la base de datos.</p>

                        <form action="{{ route('inventory.storeNewCamera') }}" method="POST" class="mt-4 space-y-4">
                            @csrf
                            <input type="hidden" name="nfc_id" value="{{ $nfc_id }}">

                            <div>
                                <label for="new_camera_model" class="mb-2 block text-sm font-medium text-emerald-900">Modelo o nombre del equipo</label>
                                <input type="text" name="modelo" id="new_camera_model" value="{{ old('modelo') }}"
                                    class="w-full rounded-xl border border-emerald-200 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                                    placeholder="Ej. Canon T7 - Kit A" required>
                            </div>

                            <div>
                                <label for="new_camera_alias" class="mb-2 block text-sm font-medium text-emerald-900">Alias</label>
                                <input type="text" name="alias" id="new_camera_alias" value="{{ old('alias') }}"
                                    class="w-full rounded-xl border border-emerald-200 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                                    placeholder="Opcional">
                            </div>

                            <div>
                                <label for="new_camera_status" class="mb-2 block text-sm font-medium text-emerald-900">Estado inicial</label>
                                <select name="estado" id="new_camera_status"
                                    class="w-full rounded-xl border border-emerald-200 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                                    required>
                                    <option value="Disponible" @selected(old('estado', 'Disponible') === 'Disponible')>Disponible</option>
                                    <option value="Prestada" @selected(old('estado') === 'Prestada')>Prestada</option>
                                    <option value="Mantenimiento" @selected(old('estado') === 'Mantenimiento')>Mantenimiento</option>
                                </select>
                            </div>

                            <button type="submit"
                                class="w-full rounded-xl bg-amber-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-amber-700">
                                Crear camara y asignar tag
                            </button>
                        </form>
                    </div>
                </div>
            </section>

        </div>

        <div class="mt-8 text-center">
            <a href="{{ route('inventory.index') }}" class="ui-back-link">Cancelar y volver al inicio</a>
        </div>

    </div>

    <form action="{{ route('inventory.scan') }}" method="POST" id="nfc_form">
        @csrf
        <input type="hidden" name="telemetry_session_uuid" id="telemetry_session_uuid">
        <input type="text" name="nfc_id" id="nfc_input" autocomplete="off"
            style="opacity: 0; position: absolute; top: -1000px; left: -1000px;">
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const nfcInput = document.getElementById('nfc_input');
            const nfcForm = document.getElementById('nfc_form');
            const scannerStatusBadge = document.getElementById('scanner_status_badge');
            const scannerStatusText = document.getElementById('scanner_status_text');
            const scannerLastScan = document.getElementById('scanner_last_scan');
            const telemetryRegisterSession = document.getElementById('telemetry_register_session');
            if (!nfcInput || !nfcForm) return;

            const MIN_SCAN_LENGTH = 4;
            const SCAN_IDLE_SUBMIT_MS = 120;
            const SCAN_RESET_GAP_MS = 350;
            const SCAN_MAX_KEY_INTERVAL_MS = 80;

            let buffer = '';
            let lastKeyAt = 0;
            let flushTimer = null;
            let submitPending = false;
            const READER_EVENT_STORAGE_KEY = 'hardware-telemetry-last-reader-event';
            const telemetry = window.HardwareTelemetryPage
                ? window.HardwareTelemetryPage({
                    collectUrl: @json(route('inventory.telemetry.collect')),
                    snapshotUrl: @json(route('inventory.telemetry.snapshot')),
                    snapshotMs: 1200,
                    pageName: 'register',
                    pagePath: window.location.pathname,
                    pageUrl: window.location.href,
                    source: 'inventory-register',
                    sessionFieldId: 'telemetry_session_uuid',
                    onSnapshot: (snapshot, sessionState) => {
                        if (telemetryRegisterSession) {
                            telemetryRegisterSession.textContent = `Sesion telemetrica: ${sessionState.session_uuid.slice(0, 8)}...`;
                        }
                        reactToReaderEvent(snapshot);
                    },
                })
                : null;

            const isEditable = (el) => {
                if (!el) return false;
                const tag = (el.tagName || '').toUpperCase();
                return el.isContentEditable || tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
            };

            const normalizeRawScan = (raw) => (raw || '').replace(/[\r\n\t]+/g, ' ').trim();

            const setScannerStatus = (badge, text) => {
                if (scannerStatusBadge) {
                    scannerStatusBadge.textContent = badge;
                    scannerStatusBadge.className = 'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold';
                    if (badge === 'Detectando') {
                        scannerStatusBadge.classList.add('bg-sky-100', 'text-sky-800');
                    } else if (badge === 'Leido') {
                        scannerStatusBadge.classList.add('bg-emerald-100', 'text-emerald-800');
                    } else if (badge === 'Error') {
                        scannerStatusBadge.classList.add('bg-rose-100', 'text-rose-800');
                    } else {
                        scannerStatusBadge.classList.add('bg-amber-100', 'text-amber-800');
                    }
                }
                if (scannerStatusText) {
                    scannerStatusText.textContent = text;
                }
            };

            const showLastScan = (value, reason) => {
                if (!scannerLastScan) return;
                const safeValue = value && value.trim() ? value.trim() : 'sin datos';
                scannerLastScan.textContent = `Ultima lectura (${reason}): ${safeValue}`;
            };

            const reactToReaderEvent = (snapshot) => {
                const event = snapshot?.latest_reader_event;
                if (!event) {
                    return;
                }

                const eventId = event.id ? String(event.id) : '';
                const alreadyHandled = eventId && window.sessionStorage.getItem(READER_EVENT_STORAGE_KEY) === eventId;
                if (alreadyHandled) {
                    return;
                }

                const uid = event.uid || event.payload?.uid_normalized || event.payload?.uid || '';
                if (uid) {
                    showLastScan(uid, 'backend');
                }

                if (event.event_type === 'bridge.serial_connected') {
                    setScannerStatus('Esperando', event.message || 'Lector serial conectado.');
                } else if (event.event_type === 'bridge.started') {
                    setScannerStatus('Esperando', event.message || 'Bridge NFC/RFID activo.');
                } else if (event.event_type === 'bridge.serial_connection_failed') {
                    setScannerStatus('Error', event.message || 'No se pudo abrir el puerto serial del lector.');
                } else if (event.event_type === 'backend.rfid_scan.received') {
                    setScannerStatus('Detectando', event.message || 'El backend recibio un UID desde el lector serial.');
                } else if (event.event_type === 'backend.rfid_scan.unregistered') {
                    setScannerStatus('Leido', event.message || 'Tag no registrado detectado.');
                    const registerPath = event.payload?.register_path;
                    if (registerPath && registerPath !== window.location.pathname) {
                        window.sessionStorage.setItem(READER_EVENT_STORAGE_KEY, eventId);
                        window.location.assign(registerPath);
                        return;
                    }
                } else if (event.event_type === 'backend.rfid_scan.student_ok') {
                    setScannerStatus('Leido', event.message || 'Carnet detectado correctamente.');
                } else if (
                    event.event_type === 'backend.rfid_scan.student_required' ||
                    event.event_type === 'backend.rfid_scan.student_not_found' ||
                    event.event_type === 'backend.rfid_scan.camera_unavailable' ||
                    event.event_type === 'backend.rfid_scan.invalid_input'
                ) {
                    setScannerStatus('Error', event.message || 'El lector serial reporto un problema.');
                }

                if (eventId) {
                    window.sessionStorage.setItem(READER_EVENT_STORAGE_KEY, eventId);
                }
            };

            const keepScannerFocus = () => {
                const active = document.activeElement;
                if (!isEditable(active) || active === nfcInput) {
                    nfcInput.focus({ preventScroll: true });
                }
            };

            const submitCode = (raw, reason = 'submit') => {
                const code = normalizeRawScan(raw);
                if (!code) {
                    setScannerStatus('Error', 'Se recibio una lectura vacia.');
                    telemetry?.track('web.scan_empty', 'Se intento enviar una lectura vacia desde registro.', {
                        reason,
                    }, 'warning', 'web_ui');
                    return;
                }
                if (submitPending) return;
                submitPending = true;
                setScannerStatus('Leido', `Tag detectado: ${code}. Enviando...`);
                showLastScan(code, reason);
                nfcInput.value = code;
                telemetry?.track('web.scan_submit_requested', 'La pagina de registro va a enviar un tag escaneado.', {
                    uid: code,
                    reason,
                }, 'info', 'web_ui');
                nfcForm.submit();
            };

            const flushBuffer = (reason = 'flush') => {
                if (flushTimer) {
                    clearTimeout(flushTimer);
                    flushTimer = null;
                }
                const value = normalizeRawScan(buffer || nfcInput.value || '');
                buffer = '';
                nfcInput.value = '';
                if (value.length >= MIN_SCAN_LENGTH) {
                    submitCode(value, reason);
                } else if (value.length > 0) {
                    setScannerStatus('Error', `Se detecto una trama corta: "${value}"`);
                    showLastScan(value, reason);
                    telemetry?.track('web.scan_short_frame', 'Se detecto una trama corta en registro.', {
                        raw_value: value,
                        reason,
                    }, 'warning', 'web_ui');
                }
            };

            const scheduleFlush = () => {
                if (flushTimer) clearTimeout(flushTimer);
                flushTimer = setTimeout(() => flushBuffer('timeout'), SCAN_IDLE_SUBMIT_MS);
            };

            if (window.NfcScanner) {
                new window.NfcScanner('nfc_input');
                nfcInput.addEventListener('nfc:scan', (e) => {
                    const id = e.detail?.id || '';
                    telemetry?.track('web.scan_custom_event', 'Registro recibio un evento custom del lector.', {
                        uid: id,
                    }, 'info', 'web_ui');
                    submitCode(id, 'custom-event');
                });
            }

            nfcInput.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter' && e.key !== 'NumpadEnter' && e.key !== 'Tab') return;
                e.preventDefault();
                flushBuffer('input-enter');
            });

            nfcInput.addEventListener('input', () => {
                const currentValue = normalizeRawScan(nfcInput.value);
                if (!currentValue) return;
                setScannerStatus('Detectando', 'Llegaron caracteres al input del lector.');
                showLastScan(currentValue, 'input');
                telemetry?.track('web.scan_input_detected', 'El input oculto de registro recibio caracteres.', {
                    raw_value: currentValue,
                    method: 'input',
                }, 'info', 'web_ui');
                scheduleFlush();
            });

            document.addEventListener('paste', (e) => {
                const pasted = normalizeRawScan(e.clipboardData?.getData('text') || '');
                if (!pasted) return;
                e.preventDefault();
                buffer = pasted;
                nfcInput.value = pasted;
                setScannerStatus('Detectando', 'El lector envio datos como pegado rapido.');
                showLastScan(pasted, 'paste');
                telemetry?.track('web.scan_input_detected', 'Registro detecto una lectura por pegado rapido.', {
                    raw_value: pasted,
                    method: 'paste',
                }, 'info', 'web_ui');
                scheduleFlush();
            }, true);

            document.addEventListener('keydown', (e) => {
                if (e.defaultPrevented || e.ctrlKey || e.metaKey || e.altKey) return;

                const now = Date.now();
                const gap = now - lastKeyAt;
                if (gap > SCAN_RESET_GAP_MS) {
                    buffer = '';
                    nfcInput.value = '';
                }
                lastKeyAt = now;

                const active = document.activeElement;
                const activeIsScannerInput = active === nfcInput;
                const activeIsEditable = isEditable(active) && !activeIsScannerInput;
                const looksLikeScannerBurst = gap > 0 && gap <= SCAN_MAX_KEY_INTERVAL_MS;

                if (e.key === 'Enter' || e.key === 'NumpadEnter' || e.key === 'Tab') {
                    if (buffer || nfcInput.value) {
                        e.preventDefault();
                        flushBuffer('keydown-terminator');
                    }
                    return;
                }

                if (e.key === 'Backspace') {
                    if (activeIsEditable && !looksLikeScannerBurst) return;
                    buffer = buffer.slice(0, -1);
                    nfcInput.value = buffer;
                    setScannerStatus('Detectando', 'Llegaron teclas del lector.');
                    showLastScan(buffer, 'keydown');
                    scheduleFlush();
                    return;
                }

                if (e.key.length === 1) {
                    if (activeIsEditable && !looksLikeScannerBurst) return;
                    buffer += e.key;
                    nfcInput.value = buffer;
                    setScannerStatus('Detectando', 'Llegaron teclas del lector.');
                    showLastScan(buffer, 'keydown');
                    scheduleFlush();
                }
            }, true);

            window.addEventListener('focus', keepScannerFocus);
            window.addEventListener('pageshow', keepScannerFocus);
            document.addEventListener('click', () => setTimeout(keepScannerFocus, 0));
            setInterval(keepScannerFocus, 1000);
            setScannerStatus('Esperando', 'La pagina esta escuchando teclado USB, pegado rapido y cambios directos en el input.');
            telemetry?.init();
            telemetry?.track('web.page_ready', 'La pagina de registro quedo escuchando el lector.', {
                current_nfc_id: @json($nfc_id),
            }, 'info', 'web_ui');
            keepScannerFocus();
        });
    </script>
</body>

</html>


