import { createHardwareTelemetryPage } from "./hardwareTelemetry.js";
import { byId, mountScannerWorkflow, normalizeRawScan, setBadgeState } from "./shared.js";

document.addEventListener("DOMContentLoaded", () => {
  const pageConfig = byId<HTMLElement>("register-page-config");
  const input = byId<HTMLInputElement>("nfc_input");
  const form = byId<HTMLFormElement>("nfc_form");
  const badge = byId<HTMLElement>("scanner_status_badge");
  const statusText = byId<HTMLElement>("scanner_status_text");
  const lastScan = byId<HTMLElement>("scanner_last_scan");
  const telemetryRegisterSession = byId<HTMLElement>("telemetry_register_session");

  if (!pageConfig || !input || !form) {
    return;
  }

  const telemetry = createHardwareTelemetryPage({
    collectUrl: pageConfig.dataset.collectUrl ?? "",
    snapshotUrl: pageConfig.dataset.snapshotUrl ?? "",
    pageName: "register",
    pagePath: window.location.pathname,
    pageUrl: window.location.href,
    source: "inventory-register",
    sessionFieldId: "telemetry_session_uuid",
    onSnapshot: (_, sessionState) => {
      if (telemetryRegisterSession) {
        telemetryRegisterSession.textContent = `Sesion telemetrica: ${sessionState.session_uuid.slice(0, 8)}...`;
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
    pageReadyMessage: "La pagina de registro quedo escuchando el lector.",
    pageReadyPayload: { current_nfc_id: pageConfig.dataset.currentNfcId ?? "" },
  });

  telemetry.init();
});
