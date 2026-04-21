import { createHardwareTelemetryPage } from "./hardwareTelemetry.js";
import {
  byId,
  escapeHtml,
  formatDateTime,
  mountScannerWorkflow,
  normalizeRawScan,
  setBadgeState,
} from "./shared.js";

const actionableEventTypes = ["backend.rfid_scan.loan_ok", "backend.rfid_scan.return_ok"];
const actionEventStorageKey = "hardware-telemetry-last-action-event";

document.addEventListener("DOMContentLoaded", () => {
  const pageConfig = byId<HTMLElement>("inventory-page-config");
  const input = byId<HTMLInputElement>("nfc_input");
  const form = byId<HTMLFormElement>("nfc_form");
  const badge = byId<HTMLElement>("scanner_status_badge");
  const statusText = byId<HTMLElement>("scanner_status_text");
  const lastScan = byId<HTMLElement>("scanner_last_scan");
  const telemetrySessionId = byId<HTMLElement>("telemetry_session_id");
  const telemetrySessionWindow = byId<HTMLElement>("telemetry_session_window");
  const telemetryReaderStatus = byId<HTMLElement>("telemetry_reader_status");
  const telemetryReaderSource = byId<HTMLElement>("telemetry_reader_source");
  const telemetryBridgeLocalStatus = byId<HTMLElement>("telemetry_bridge_local_status");
  const telemetryActiveStudent = byId<HTMLElement>("telemetry_active_student");
  const telemetryRecentEvents = byId<HTMLElement>("telemetry_recent_events");
  const telemetryServerTime = byId<HTMLElement>("telemetry_server_time");

  if (!pageConfig || !input || !form) {
    return;
  }

  const initialReaderSource = pageConfig.dataset.initialReaderSource ?? "sin detectar";
  let lastSnapshotErrorAt = 0;

  const telemetry = createHardwareTelemetryPage({
    collectUrl: pageConfig.dataset.collectUrl ?? "",
    snapshotUrl: pageConfig.dataset.snapshotUrl ?? "",
    pageName: "inventory",
    pagePath: window.location.pathname,
    pageUrl: window.location.href,
    source: "inventory-web",
    sessionFieldId: "telemetry_session_uuid",
    onSnapshot: (snapshot, sessionState) => {
      if (telemetrySessionId) {
        telemetrySessionId.textContent = sessionState.session_uuid.slice(0, 8) + "...";
      }
      if (telemetrySessionWindow) {
        telemetrySessionWindow.textContent =
          `Inicio: ${formatDateTime(snapshot?.browser_session?.started_at || sessionState.started_at)} | ` +
          `Ultima actividad: ${formatDateTime(snapshot?.browser_session?.last_seen_at || sessionState.last_seen_at)}`;
      }
      if (telemetryReaderStatus) {
        const readerEvent = snapshot?.latest_reader_event;
        telemetryReaderStatus.textContent = readerEvent
          ? `${readerEvent.event_type}: ${readerEvent.message || "sin mensaje"}`
          : "Esperando eventos backend/RFID.";
      }
      if (telemetryReaderSource) {
        const activeBridge = snapshot?.active_bridge_sessions?.[0];
        const readerSource = activeBridge?.source || snapshot?.latest_reader_event?.source || initialReaderSource;
        telemetryReaderSource.textContent = `Fuente: ${readerSource || "sin detectar"}`;
      }
      if (telemetryBridgeLocalStatus) {
        const bridgeLogEvents = snapshot?.bridge_log_events || [];
        const latestBridgeLog = bridgeLogEvents.length ? bridgeLogEvents[bridgeLogEvents.length - 1] : null;
        telemetryBridgeLocalStatus.textContent = latestBridgeLog
          ? `${latestBridgeLog.event_type}: ${latestBridgeLog.message || "sin mensaje"} (${formatDateTime(latestBridgeLog.timestamp)})`
          : "Sin archivo local aun.";
      }
      if (telemetryActiveStudent) {
        const activeBridge = snapshot?.active_bridge_sessions?.[0];
        telemetryActiveStudent.textContent = activeBridge?.active_student
          ? `${activeBridge.active_student.nombre} (${activeBridge.source || "sin fuente"})`
          : "Ninguno.";
      }
      if (telemetryServerTime) {
        telemetryServerTime.textContent = `Servidor: ${formatDateTime(snapshot?.server_time)}`;
      }
      if (telemetryRecentEvents) {
        const events = snapshot?.recent_events || [];
        telemetryRecentEvents.innerHTML =
          events.length === 0
            ? '<li class="event-row event-row-empty">Aun no hay eventos recientes guardados.</li>'
            : events
                .slice(0, 8)
                .map((event: any) => {
                  const when = formatDateTime(event.occurred_at);
                  const source = event.source ? ` | ${escapeHtml(event.source)}` : "";
                  const sessionType = event.session_type ? ` | ${escapeHtml(event.session_type)}` : "";
                  const message = event.message || event.event_type;
                  return `
                    <li class="event-row">
                      <p class="mono">${escapeHtml(event.event_type)} | ${when}${source}${sessionType}</p>
                      <p>${escapeHtml(message)}</p>
                    </li>
                  `;
                })
                .join("");
      }

      const event = snapshot?.latest_reader_event;
      if (!event || !actionableEventTypes.includes(event.event_type) || !event.id) {
        return;
      }
      const knownEventId = sessionStorage.getItem(actionEventStorageKey);
      if (knownEventId === String(event.id)) {
        return;
      }
      sessionStorage.setItem(actionEventStorageKey, String(event.id));
      window.setTimeout(() => window.location.reload(), 900);
    },
    onSnapshotError: () => {
      const now = Date.now();
      if (now - lastSnapshotErrorAt < 8_000) {
        return;
      }
      lastSnapshotErrorAt = now;
      if (telemetryReaderStatus) {
        telemetryReaderStatus.textContent = "No se pudo consultar la telemetria del backend en este momento.";
      }
    },
  });

  mountScannerWorkflow({
    input,
    form,
    onStatus: (state, text) => {
      setBadgeState(
        badge,
        state,
        state === "detectando" ? "Detectando" : state === "leido" ? "Leido" : state === "error" ? "Error" : "Esperando",
      );
      if (statusText) {
        statusText.textContent = text;
      }
    },
    onLastScan: (value, reason) => {
      if (lastScan) {
        lastScan.textContent = `Ultima lectura (${reason}): ${normalizeRawScan(value) || "sin datos"}`;
      }
    },
    onSubmit: () => undefined,
    track: telemetry.track,
    pageReadyMessage: "La pagina de inventario quedo escuchando el lector.",
    pageReadyPayload: { reader_source_hint: initialReaderSource },
  });

  telemetry.init();
});
