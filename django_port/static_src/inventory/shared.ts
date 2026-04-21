export type TelemetryTrack = (
  eventType: string,
  message: string,
  payload?: Record<string, unknown>,
  level?: string,
  channel?: string,
) => void;

export function byId<T extends HTMLElement>(id: string): T | null {
  return document.getElementById(id) as T | null;
}

export function normalizeRawScan(raw: string | null | undefined): string {
  return (raw ?? "").replace(/[\r\n\t]+/g, " ").trim();
}

export function isEditable(el: Element | null): boolean {
  if (!(el instanceof HTMLElement)) {
    return false;
  }
  const tag = el.tagName.toUpperCase();
  return el.isContentEditable || tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT";
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) {
    return "sin datos";
  }
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) {
    return value;
  }
  return parsed.toLocaleString("es-CO");
}

export function escapeHtml(value: string | null | undefined): string {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

export function setBadgeState(
  badge: HTMLElement | null,
  state: "waiting" | "detectando" | "leido" | "error",
  text: string,
): void {
  if (!badge) {
    return;
  }

  const nextClass =
    state === "detectando"
      ? "badge badge-detectando"
      : state === "leido"
        ? "badge badge-leido"
        : state === "error"
          ? "badge badge-error"
          : "badge badge-waiting";

  badge.className = nextClass;
  badge.textContent = text;
}

interface ScannerWorkflowOptions {
  input: HTMLInputElement;
  form: HTMLFormElement;
  onStatus: (state: "waiting" | "detectando" | "leido" | "error", text: string) => void;
  onLastScan: (value: string, reason: string) => void;
  onSubmit: (code: string, reason: string) => void;
  track?: TelemetryTrack;
  pageReadyMessage: string;
  pageReadyPayload?: Record<string, unknown>;
}

export function mountScannerWorkflow(options: ScannerWorkflowOptions): void {
  const { input, form, onStatus, onLastScan, onSubmit, track } = options;
  const minScanLength = 4;
  const idleSubmitMs = 120;
  const resetGapMs = 350;
  const maxKeyIntervalMs = 80;

  let buffer = "";
  let lastKeyAt = 0;
  let flushTimer: number | null = null;
  let submitPending = false;

  const keepScannerFocus = (): void => {
    const active = document.activeElement;
    if (!isEditable(active) || active === input) {
      input.focus({ preventScroll: true });
    }
  };

  const submitCode = (raw: string, reason = "submit"): void => {
    const code = normalizeRawScan(raw);
    if (!code) {
      onStatus("error", "Se recibio una lectura vacia.");
      track?.("web.scan_empty", "Se intento enviar una lectura vacia.", { reason }, "warning", "web_ui");
      return;
    }
    if (submitPending) {
      return;
    }
    submitPending = true;
    onStatus("leido", `Tag detectado: ${code}. Enviando...`);
    onLastScan(code, reason);
    input.value = code;
    track?.(
      "web.scan_submit_requested",
      "La pagina va a enviar un tag escaneado.",
      { uid: code, reason },
      "info",
      "web_ui",
    );
    onSubmit(code, reason);
    form.submit();
  };

  const flushBuffer = (reason = "flush"): void => {
    if (flushTimer !== null) {
      window.clearTimeout(flushTimer);
      flushTimer = null;
    }
    const value = normalizeRawScan(buffer || input.value);
    buffer = "";
    input.value = "";

    if (value.length >= minScanLength) {
      submitCode(value, reason);
      return;
    }

    if (value.length > 0) {
      onStatus("error", `Se detecto una trama corta: "${value}"`);
      onLastScan(value, reason);
      track?.(
        "web.scan_short_frame",
        "Se detecto una trama demasiado corta para ser UID valido.",
        { raw_value: value, reason },
        "warning",
        "web_ui",
      );
    }
  };

  const scheduleFlush = (): void => {
    if (flushTimer !== null) {
      window.clearTimeout(flushTimer);
    }
    flushTimer = window.setTimeout(() => flushBuffer("timeout"), idleSubmitMs);
  };

  input.addEventListener("keydown", (event) => {
    if (!["Enter", "NumpadEnter", "Tab"].includes(event.key)) {
      return;
    }
    event.preventDefault();
    flushBuffer("input-enter");
  });

  input.addEventListener("input", () => {
    const currentValue = normalizeRawScan(input.value);
    if (!currentValue) {
      return;
    }
    onStatus("detectando", "Llegaron caracteres al input del lector.");
    onLastScan(currentValue, "input");
    track?.(
      "web.scan_input_detected",
      "El input oculto recibio caracteres del lector.",
      { raw_value: currentValue, method: "input" },
      "info",
      "web_ui",
    );
    scheduleFlush();
  });

  document.addEventListener(
    "paste",
    (event) => {
      const pasted = normalizeRawScan(event.clipboardData?.getData("text"));
      if (!pasted) {
        return;
      }
      event.preventDefault();
      buffer = pasted;
      input.value = pasted;
      onStatus("detectando", "El lector envio datos como pegado rapido.");
      onLastScan(pasted, "paste");
      track?.(
        "web.scan_input_detected",
        "La pagina detecto una lectura por pegado rapido.",
        { raw_value: pasted, method: "paste" },
        "info",
        "web_ui",
      );
      scheduleFlush();
    },
    true,
  );

  document.addEventListener(
    "keydown",
    (event) => {
      if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey) {
        return;
      }

      const now = Date.now();
      const gap = now - lastKeyAt;
      if (gap > resetGapMs) {
        buffer = "";
        input.value = "";
      }
      lastKeyAt = now;

      const active = document.activeElement;
      const activeIsScannerInput = active === input;
      const activeIsEditable = isEditable(active) && !activeIsScannerInput;
      const looksLikeScannerBurst = gap > 0 && gap <= maxKeyIntervalMs;

      if (["Enter", "NumpadEnter", "Tab"].includes(event.key)) {
        if (buffer || input.value) {
          event.preventDefault();
          flushBuffer("keydown-terminator");
        }
        return;
      }

      if (event.key === "Backspace") {
        if (activeIsEditable && !looksLikeScannerBurst) {
          return;
        }
        buffer = buffer.slice(0, -1);
        input.value = buffer;
        onStatus("detectando", "Llegaron teclas del lector.");
        onLastScan(buffer, "keydown");
        scheduleFlush();
        return;
      }

      if (event.key.length === 1) {
        if (activeIsEditable && !looksLikeScannerBurst) {
          return;
        }
        buffer += event.key;
        input.value = buffer;
        onStatus("detectando", "Llegaron teclas del lector.");
        onLastScan(buffer, "keydown");
        scheduleFlush();
      }
    },
    true,
  );

  window.addEventListener("focus", keepScannerFocus);
  window.addEventListener("pageshow", keepScannerFocus);
  document.addEventListener("click", () => window.setTimeout(keepScannerFocus, 0));
  window.setInterval(keepScannerFocus, 1000);
  onStatus("waiting", "La pagina esta escuchando teclado USB, pegado rapido y cambios directos en el input.");
  track?.("web.page_ready", options.pageReadyMessage, options.pageReadyPayload ?? {}, "info", "web_ui");
  keepScannerFocus();
}
