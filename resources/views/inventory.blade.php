<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario de Camaras - Fotografia</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="{{ asset('js/hardware-telemetry.js') }}"></script>
    @include('partials.unified-ui-head')
    <style>
        #nfc_input {
            opacity: 0.5;
            position: absolute;
            top: -100px;
        }

        .status-dot {
            height: 15px;
            width: 15px;
            border-radius: 50%;
            display: inline-block;
        }
    </style>
</head>

<body class="ui-body">

    <div class="ui-shell">

        <div class="ui-header-card mb-8">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p class="ui-kicker">Operacion diaria</p>
                    <h1 class="ui-title">Control de Camaras</h1>
                    <p class="ui-subtitle">
                        Gestiona inventario, registro de tags, historial y telemetria desde un solo lugar.
                    </p>
                </div>
                <div class="ui-actions">
                    <a href="{{ route('inventory.workflow') }}" class="ui-button ui-button--secondary">
                        Guia de uso
                    </a>
                    <a href="{{ route('inventory.telemetry.index') }}" class="ui-button ui-button--secondary">
                        Telemetria
                    </a>
                    <a href="{{ route('historial') }}" class="ui-button ui-button--accent">
                        Ver historial
                    </a>
                </div>
            </div>
        </div>

        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-stretch lg:justify-between">
            <div class="ui-stat-card ui-stat-card--primary lg:max-w-2xl">
                <p class="ui-kicker">Panel activo</p>
                <p class="text-lg font-extrabold text-slate-800">Inventario operativo</p>
                <p class="mt-2 text-sm text-slate-600">
                    Consulta disponibilidad y responde al lector desde cualquier dispositivo conectado a la misma red.
                </p>
            </div>
            <div class="ui-stat-card ui-stat-card--accent min-w-[300px] text-center">
                @if(session('estudiante_actual'))
                    <p id="student_panel_title" class="text-xl font-bold text-green-600">
                        Hola, {{ session('estudiante_actual')->nombre }}
                    </p>
                    <p id="student_panel_hint" class="text-xs text-gray-500">Escanea una camara para pedir/devolver</p>
                @else
                    <p id="student_panel_title" class="font-semibold text-gray-500">Esperando estudiante...</p>
                    <p id="student_panel_hint" class="text-xs text-gray-400">Escanea tu carnet para comenzar</p>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 border-l-4 border-green-500 bg-green-100 p-4 text-green-700" role="alert">
                <p>{{ session('success') }}</p>
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 border-l-4 border-red-500 bg-red-100 p-4 text-red-700" role="alert">
                <p>{{ session('error') }}</p>
            </div>
        @endif

        <form action="{{ route('inventory.scan') }}" method="POST" id="nfc_form">
            @csrf
            <input type="text" name="nfc_id" id="nfc_input" autocomplete="off" autofocus>
            <input type="hidden" name="telemetry_session_uuid" id="telemetry_session_uuid">
        </form>

        <div class="mb-4 text-center">
            <p class="text-sm text-gray-400">LECTOR NFC/RFID ACTIVO</p>
            <p class="mt-1 text-xs text-gray-500">
                Abre esta URL desde otro dispositivo en la misma red:
                <span class="font-mono text-gray-700">{{ request()->getSchemeAndHttpHost() . route('inventory.index', [], false) }}</span>
            </p>
        </div>

        <div class="ui-card mb-6">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-slate-700">Estado del lector</p>
                <span id="scanner_status_badge"
                    class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                    Esperando
                </span>
            </div>
            <p id="scanner_status_text" class="mt-2 text-sm text-slate-600">
                La pagina esta lista. Un lector conectado a este servidor tambien se reflejara aqui aunque abras la web desde otro dispositivo en la misma red.
            </p>
            <p id="scanner_last_scan" class="mt-1 font-mono text-xs text-slate-500">
                Ultima lectura: sin datos.
            </p>
            <p id="scanner_reader_hint" class="mt-1 text-xs text-slate-400">
                Fuente: {{ $latestBridgeSession?->source ?? 'sin detectar' }}
                | Lector: {{ strtoupper((string) data_get($latestBridgeSession?->metadata, 'reader_model', 'auto')) }}
            </p>
            <div class="mt-3 flex justify-end">
                <a href="{{ route('inventory.telemetry.index') }}"
                    class="ui-button ui-button--secondary text-xs uppercase tracking-[0.18em]">
                    Ver telemetria operativa
                </a>
            </div>
        </div>

        <div class="ui-table-card">
            <div class="border-b border-[var(--ui-line)] px-6 py-5">
                <p class="ui-kicker">Inventario</p>
                <h2 class="ui-section-title">Estado actual de las camaras</h2>
                <p class="ui-section-copy">Disponibilidad, mantenimiento y codigos NFC listos para consulta.</p>
            </div>
            <div class="ui-table-wrap">
            <table class="ui-data-table leading-normal">
                <thead>
                    <tr>
                        <th>
                            Modelo
                        </th>
                        <th>
                            Estado
                        </th>
                        <th>
                            ID NFC
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($camaras as $camara)
                        <tr>
                            <td class="text-sm">
                                <p class="whitespace-no-wrap font-bold text-gray-900">
                                    {{ str_replace('Canon T7', 'Camara', $camara->modelo) }}
                                </p>
                            </td>
                            <td class="text-sm">
                                @if($camara->estado === 'Disponible')
                                    <span class="relative inline-block px-3 py-1 font-semibold leading-tight text-green-900">
                                        <span aria-hidden="true"
                                            class="absolute inset-0 rounded-full bg-green-200 opacity-50"></span>
                                        <span class="relative">Disponible</span>
                                    </span>
                                @elseif($camara->estado === 'Prestada')
                                    <span class="relative inline-block px-3 py-1 font-semibold leading-tight text-red-900">
                                        <span aria-hidden="true"
                                            class="absolute inset-0 rounded-full bg-red-200 opacity-50"></span>
                                        <span class="relative">Prestada</span>
                                    </span>
                                @else
                                    <span class="relative inline-block px-3 py-1 font-semibold leading-tight text-orange-900">
                                        <span aria-hidden="true"
                                            class="absolute inset-0 rounded-full bg-orange-200 opacity-50"></span>
                                        <span class="relative">Mantenimiento</span>
                                    </span>
                                @endif
                            </td>
                            <td class="text-sm">
                                <p class="whitespace-no-wrap font-mono text-gray-500">{{ $camara->nfc_id }}</p>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const nfcInput = document.getElementById('nfc_input');
            const nfcForm = document.getElementById('nfc_form');
            const scannerStatusBadge = document.getElementById('scanner_status_badge');
            const scannerStatusText = document.getElementById('scanner_status_text');
            const scannerLastScan = document.getElementById('scanner_last_scan');
            const studentPanelTitle = document.getElementById('student_panel_title');
            const studentPanelHint = document.getElementById('student_panel_hint');
            const telemetrySessionId = document.getElementById('telemetry_session_id');
            const telemetrySessionWindow = document.getElementById('telemetry_session_window');
            const telemetryReaderStatus = document.getElementById('telemetry_reader_status');
            const telemetryReaderSource = document.getElementById('telemetry_reader_source');
            const telemetryBridgeLocalStatus = document.getElementById('telemetry_bridge_local_status');
            const telemetryActiveStudent = document.getElementById('telemetry_active_student');
            const telemetryRecentEvents = document.getElementById('telemetry_recent_events');
            const telemetryServerTime = document.getElementById('telemetry_server_time');
            if (!nfcInput || !nfcForm) return;

            const MIN_SCAN_LENGTH = 4;
            const SCAN_IDLE_SUBMIT_MS = 120;
            const SCAN_RESET_GAP_MS = 350;
            const SCAN_MAX_KEY_INTERVAL_MS = 80;
            const ACTIONABLE_EVENT_TYPES = ['backend.rfid_scan.loan_ok', 'backend.rfid_scan.return_ok'];
            const ACTION_EVENT_STORAGE_KEY = 'hardware-telemetry-last-action-event';
            const READER_EVENT_STORAGE_KEY = 'hardware-telemetry-last-reader-event';
            const HAS_LOCAL_WEB_STUDENT = @json((bool) session('estudiante_actual'));

            let buffer = '';
            let lastKeyAt = 0;
            let flushTimer = null;
            let submitPending = false;
            let lastSnapshotErrorAt = 0;

            const initialReaderSource = @json($latestBridgeSession?->source);
            const telemetry = window.HardwareTelemetryPage
                ? window.HardwareTelemetryPage({
                    collectUrl: @json(route('inventory.telemetry.collect')),
                    snapshotUrl: @json(route('inventory.telemetry.snapshot')),
                    snapshotMs: 1200,
                    pageName: 'inventory',
                    pagePath: window.location.pathname,
                    pageUrl: window.location.href,
                    source: 'inventory-web',
                    sessionFieldId: 'telemetry_session_uuid',
                    onSnapshot: (snapshot, sessionState) => {
                        renderSnapshot(snapshot, sessionState);
                    },
                    onSnapshotError: () => {
                        const now = Date.now();
                        if ((now - lastSnapshotErrorAt) < 8000) {
                            return;
                        }
                        lastSnapshotErrorAt = now;
                        if (telemetryReaderStatus) {
                            telemetryReaderStatus.textContent = 'No se pudo consultar la telemetria del backend en este momento.';
                        }
                    },
                })
                : null;

            const isEditable = (el) => {
                if (!el) return false;
                const tag = (el.tagName || '').toUpperCase();
                return el.isContentEditable || tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
            };

            const normalizeRawScan = (raw) => (raw || '').replace(/[\r\n\t]+/g, ' ').trim();
            const shortSessionId = (value) => value ? `${value.slice(0, 8)}...` : 'sin dato';
            const formatDateTime = (value) => {
                if (!value) return 'sin datos';
                const parsed = new Date(value);
                if (Number.isNaN(parsed.getTime())) return value;
                return parsed.toLocaleString('es-CO');
            };
            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

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

            const renderRecentEvents = (events) => {
                if (!telemetryRecentEvents) return;
                if (!events || events.length === 0) {
                    telemetryRecentEvents.innerHTML = `
                        <li class="ui-log-item ui-log-item--empty">
                            Aun no hay eventos recientes guardados.
                        </li>
                    `;
                    return;
                }

                telemetryRecentEvents.innerHTML = events.slice(0, 8).map((event) => {
                    const when = formatDateTime(event.occurred_at);
                    const source = event.source ? ` | ${escapeHtml(event.source)}` : '';
                    const sessionType = event.session_type ? ` | ${escapeHtml(event.session_type)}` : '';
                    const message = event.message || event.event_type;
                    return `
                        <li class="ui-log-item">
                            <p class="ui-log-meta ui-mono">${escapeHtml(event.event_type)} | ${when}${source}${sessionType}</p>
                            <p class="ui-log-text">${escapeHtml(message)}</p>
                        </li>
                    `;
                }).join('');
            };

            const maybeReloadForReaderAction = (snapshot) => {
                const event = snapshot?.latest_reader_event;
                if (!event || !ACTIONABLE_EVENT_TYPES.includes(event.event_type) || !event.id) {
                    return;
                }

                const knownEventId = window.sessionStorage.getItem(ACTION_EVENT_STORAGE_KEY);
                if (knownEventId === String(event.id)) {
                    return;
                }

                window.sessionStorage.setItem(ACTION_EVENT_STORAGE_KEY, String(event.id));
                window.setTimeout(() => {
                    window.location.reload();
                }, 900);
            };

            const updateStudentPanel = (snapshot) => {
                if (!studentPanelTitle || !studentPanelHint || HAS_LOCAL_WEB_STUDENT) {
                    return;
                }

                const activeBridge = snapshot?.active_bridge_sessions?.[0];
                if (activeBridge?.active_student?.nombre) {
                    studentPanelTitle.textContent = `Hola, ${activeBridge.active_student.nombre}`;
                    studentPanelTitle.className = 'text-xl font-bold text-green-600';
                    studentPanelHint.textContent = 'Estudiante activo por lector serial. Escanea una camara.';
                    studentPanelHint.className = 'text-xs text-gray-500';
                    return;
                }

                studentPanelTitle.textContent = 'Esperando estudiante...';
                studentPanelTitle.className = 'font-semibold text-gray-500';
                studentPanelHint.textContent = 'Escanea tu carnet para comenzar';
                studentPanelHint.className = 'text-xs text-gray-400';
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
                } else if (event.event_type === 'backend.rfid_scan.student_ok') {
                    setScannerStatus('Leido', event.message || 'Carnet detectado correctamente.');
                } else if (event.event_type === 'backend.rfid_scan.unregistered') {
                    setScannerStatus('Leido', event.message || 'Tag no registrado detectado por lector serial.');
                    const registerPath = event.payload?.register_path;
                    if (registerPath && window.location.pathname !== registerPath) {
                        if (eventId) {
                            window.sessionStorage.setItem(READER_EVENT_STORAGE_KEY, eventId);
                        }
                        window.location.assign(registerPath);
                        return;
                    }
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

            const renderSnapshot = (snapshot, sessionState) => {
                if (telemetrySessionId) {
                    telemetrySessionId.textContent = shortSessionId(sessionState?.session_uuid);
                }
                if (telemetrySessionWindow) {
                    telemetrySessionWindow.textContent =
                        `Inicio: ${formatDateTime(snapshot?.browser_session?.started_at || sessionState?.started_at)} | ` +
                        `Ultima actividad: ${formatDateTime(snapshot?.browser_session?.last_seen_at || sessionState?.last_seen_at)}`;
                }
                if (telemetryReaderStatus) {
                    const readerEvent = snapshot?.latest_reader_event;
                    telemetryReaderStatus.textContent = readerEvent
                        ? `${readerEvent.event_type}: ${readerEvent.message || 'sin mensaje'}`
                        : 'Esperando eventos backend/RFID.';
                }
                if (telemetryReaderSource) {
                    const activeBridge = snapshot?.active_bridge_sessions?.[0];
                    const readerSource = activeBridge?.source || snapshot?.latest_reader_event?.source || initialReaderSource || 'sin detectar';
                    const readerModel = (activeBridge?.reader_model || @json(data_get($latestBridgeSession?->metadata, 'reader_model', 'auto')) || 'auto').toUpperCase();
                    telemetryReaderSource.textContent = `Fuente: ${readerSource} | Lector: ${readerModel}`;
                }
                if (telemetryBridgeLocalStatus) {
                    const bridgeLogEvents = snapshot?.bridge_log_events || [];
                    const latestBridgeLog = bridgeLogEvents.length ? bridgeLogEvents[bridgeLogEvents.length - 1] : null;
                    telemetryBridgeLocalStatus.textContent = latestBridgeLog
                        ? `${latestBridgeLog.event_type}: ${latestBridgeLog.message || 'sin mensaje'} (${formatDateTime(latestBridgeLog.timestamp)})`
                        : 'Sin archivo local aun.';
                }
                if (telemetryActiveStudent) {
                    const activeBridge = snapshot?.active_bridge_sessions?.[0];
                    const readerLabel = activeBridge?.reader_model ? String(activeBridge.reader_model).toUpperCase() : 'AUTO';
                    telemetryActiveStudent.textContent = activeBridge?.active_student
                        ? `${activeBridge.active_student.nombre} (${activeBridge.source || 'sin fuente'} / ${readerLabel})`
                        : 'Ninguno.';
                }
                if (telemetryServerTime) {
                    telemetryServerTime.textContent = `Servidor: ${formatDateTime(snapshot?.server_time)}`;
                }
                updateStudentPanel(snapshot);
                reactToReaderEvent(snapshot);
                renderRecentEvents(snapshot?.recent_events || []);
                maybeReloadForReaderAction(snapshot);
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
                    telemetry?.track('web.scan_empty', 'Se intento enviar una lectura vacia.', {
                        reason,
                    }, 'warning', 'web_ui');
                    return;
                }
                if (submitPending) return;
                submitPending = true;
                setScannerStatus('Leido', `Tag detectado: ${code}. Enviando...`);
                showLastScan(code, reason);
                nfcInput.value = code;
                telemetry?.track('web.scan_submit_requested', 'La pagina va a enviar un tag escaneado.', {
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
                    telemetry?.track('web.scan_short_frame', 'Se detecto una trama demasiado corta para ser UID valido.', {
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
                    telemetry?.track('web.scan_custom_event', 'Se recibio un evento custom del lector.', {
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
                telemetry?.track('web.scan_input_detected', 'El input oculto recibio caracteres del lector.', {
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
                telemetry?.track('web.scan_input_detected', 'La pagina detecto una lectura por pegado rapido.', {
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
            telemetry?.track('web.page_ready', 'La pagina de inventario quedo escuchando el lector.', {
                reader_source_hint: initialReaderSource,
            }, 'info', 'web_ui');
            keepScannerFocus();
        });
    </script>
</body>

</html>
