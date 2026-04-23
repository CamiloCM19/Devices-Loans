<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telemetria Operativa - Control de Camaras</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="{{ asset('js/hardware-telemetry.js') }}"></script>
    @include('partials.unified-ui-head')
</head>

<body class="ui-body">
    <div class="ui-shell">
        <div class="ui-header-card mb-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="ui-kicker">Telemetria</p>
                    <h1 class="ui-title">Verificacion operativa del lector</h1>
                    <p class="ui-subtitle max-w-3xl">
                        Esta vista resume el estado del bridge RFID, el lector activo y los eventos utiles para diagnostico operativo.
                    </p>
                </div>
                <div class="ui-actions">
                    <a href="{{ route('inventory.index') }}"
                        class="ui-button ui-button--secondary">
                        Volver al inventario
                    </a>
                    <a href="{{ route('historial') }}"
                        class="ui-button ui-button--accent">
                        Ver historial
                    </a>
                </div>
            </div>
        </div>

        <div class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="ui-stat-card ui-stat-card--primary">
                <p class="text-xs uppercase tracking-wide text-slate-500">Sesion de monitoreo</p>
                <p id="telemetry_session_id" class="mt-2 text-sm ui-mono text-teal-700">pendiente...</p>
                <p id="telemetry_session_window" class="mt-2 text-sm text-slate-700">Sin inicializar.</p>
            </div>
            <div class="ui-stat-card">
                <p class="text-xs uppercase tracking-wide text-slate-500">Lector activo</p>
                <p id="telemetry_reader_status" class="mt-2 text-sm text-slate-800">Esperando eventos del bridge.</p>
                <p id="telemetry_reader_source" class="mt-2 text-xs text-slate-500">
                    Fuente: {{ $latestBridgeSession?->source ?? 'sin detectar' }}
                    | Lector: {{ strtoupper((string) data_get($latestBridgeSession?->metadata, 'reader_model', 'auto')) }}
                </p>
            </div>
            <div class="ui-stat-card">
                <p class="text-xs uppercase tracking-wide text-slate-500">Bridge local</p>
                <p id="telemetry_bridge_local_status" class="mt-2 text-sm text-slate-800">Sin archivo local aun.</p>
            </div>
            <div class="ui-stat-card ui-stat-card--accent">
                <p class="text-xs uppercase tracking-wide text-slate-500">Estudiante activo</p>
                <p id="telemetry_active_student" class="mt-2 text-sm text-slate-800">Ninguno.</p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.4fr_0.9fr]">
            <section class="ui-card">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">Eventos utiles recientes</p>
                        <p class="text-xs text-slate-500">Solo backend y bridge; se excluyen eventos del navegador.</p>
                    </div>
                    <p id="telemetry_server_time" class="text-xs text-slate-500">Servidor: --</p>
                </div>
                <ul id="telemetry_recent_events" class="ui-log-list">
                    <li class="ui-log-item ui-log-item--empty">
                        Esperando eventos recientes...
                    </li>
                </ul>
            </section>

            <section class="ui-card">
                <p class="text-sm font-semibold text-slate-800">Estado del bridge</p>
                <p class="mt-1 text-xs text-slate-500">Archivo local del servidor o, si no existe, actividad remota reciente del lector.</p>
                <ul id="telemetry_bridge_events" class="ui-log-list">
                    <li class="ui-log-item ui-log-item--empty">
                        Sin archivo local aun.
                    </li>
                </ul>
            </section>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const telemetrySessionId = document.getElementById('telemetry_session_id');
            const telemetrySessionWindow = document.getElementById('telemetry_session_window');
            const telemetryReaderStatus = document.getElementById('telemetry_reader_status');
            const telemetryReaderSource = document.getElementById('telemetry_reader_source');
            const telemetryBridgeLocalStatus = document.getElementById('telemetry_bridge_local_status');
            const telemetryActiveStudent = document.getElementById('telemetry_active_student');
            const telemetryRecentEvents = document.getElementById('telemetry_recent_events');
            const telemetryBridgeEvents = document.getElementById('telemetry_bridge_events');
            const telemetryServerTime = document.getElementById('telemetry_server_time');

            const initialReaderSource = @json($latestBridgeSession?->source);
            const initialReaderModel = @json(strtoupper((string) data_get($latestBridgeSession?->metadata, 'reader_model', 'auto')));

            const formatDateTime = (value) => {
                if (!value) return 'sin datos';
                const parsed = new Date(value);
                if (Number.isNaN(parsed.getTime())) return value;
                return parsed.toLocaleString('es-CO');
            };

            const shortSessionId = (value) => value ? `${value.slice(0, 8)}...` : 'sin dato';
            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const renderRecentEvents = (events) => {
                if (!telemetryRecentEvents) return;
                if (!events || events.length === 0) {
                    telemetryRecentEvents.innerHTML = `
                        <li class="ui-log-item ui-log-item--empty">
                            Aun no hay eventos operativos recientes.
                        </li>
                    `;
                    return;
                }

                telemetryRecentEvents.innerHTML = events.map((event) => {
                    const when = formatDateTime(event.occurred_at);
                    const source = event.source ? ` | ${escapeHtml(event.source)}` : '';
                    return `
                        <li class="ui-log-item">
                            <p class="ui-log-meta ui-mono">${escapeHtml(event.event_type)} | ${when}${source}</p>
                            <p class="ui-log-text">${escapeHtml(event.message || 'sin mensaje')}</p>
                        </li>
                    `;
                }).join('');
            };

            const renderBridgeLog = (events) => {
                if (!telemetryBridgeEvents || !telemetryBridgeLocalStatus) return;
                if (!events || events.length === 0) {
                    telemetryBridgeLocalStatus.textContent = 'Sin archivo local aun.';
                    telemetryBridgeEvents.innerHTML = `
                        <li class="ui-log-item ui-log-item--empty">
                            Sin archivo local aun.
                        </li>
                    `;
                    return;
                }

                const latest = events[events.length - 1];
                telemetryBridgeLocalStatus.textContent = `${latest.event_type}: ${latest.message || 'sin mensaje'} (${formatDateTime(latest.timestamp)})`;
                telemetryBridgeEvents.innerHTML = events.map((event) => `
                    <li class="ui-log-item">
                        <p class="ui-log-meta ui-mono">${escapeHtml(event.event_type)} | ${formatDateTime(event.timestamp)}</p>
                        <p class="ui-log-text">${escapeHtml(event.message || 'sin mensaje')}</p>
                    </li>
                `).join('');
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
                        : 'Esperando eventos del bridge.';
                }

                if (telemetryReaderSource) {
                    const activeBridge = snapshot?.active_bridge_sessions?.[0];
                    const readerSource = activeBridge?.source || snapshot?.latest_reader_event?.source || initialReaderSource || 'sin detectar';
                    const readerModel = (activeBridge?.reader_model || initialReaderModel || 'AUTO').toString().toUpperCase();
                    telemetryReaderSource.textContent = `Fuente: ${readerSource} | Lector: ${readerModel}`;
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

                renderRecentEvents(snapshot?.recent_events || []);
                renderBridgeLog(snapshot?.bridge_log_events || []);
            };

            const telemetry = window.HardwareTelemetryPage
                ? window.HardwareTelemetryPage({
                    collectUrl: @json(route('inventory.telemetry.collect')),
                    snapshotUrl: @json(route('inventory.telemetry.snapshot')),
                    snapshotMs: 1500,
                    snapshotLimit: 12,
                    pageName: 'telemetry',
                    pagePath: window.location.pathname,
                    pageUrl: window.location.href,
                    source: 'inventory-telemetry',
                    onSnapshot: renderSnapshot,
                })
                : null;

            telemetry?.init();
        });
    </script>
</body>

</html>
