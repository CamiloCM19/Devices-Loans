function safeParse(json, fallbackValue) {
    if (!json) {
        return fallbackValue;
    }
    try {
        return (JSON.parse(json) ?? fallbackValue);
    }
    catch {
        return fallbackValue;
    }
}
function nowIso() {
    return new Date().toISOString();
}
function createUuid() {
    if (typeof crypto.randomUUID === "function") {
        return crypto.randomUUID();
    }
    return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (char) => {
        const random = (Math.random() * 16) | 0;
        const value = char === "x" ? random : ((random & 0x3) | 0x8);
        return value.toString(16);
    });
}
function debounce(fn, delayMs) {
    let timer = null;
    return ((...args) => {
        if (timer !== null) {
            window.clearTimeout(timer);
        }
        timer = window.setTimeout(() => {
            fn(...args);
        }, delayMs);
    });
}
export function createHardwareTelemetryPage(options) {
    const config = {
        sessionStorageKey: "hardware-telemetry-session-v1",
        queueStorageKey: "hardware-telemetry-queue-v1",
        sessionTimeoutMs: 60000,
        heartbeatMs: 15000,
        snapshotMs: 4000,
        snapshotLimit: 20,
        batchSize: 20,
        maxQueuedEvents: 200,
        ...options,
    };
    let flushInFlight = false;
    let heartbeatTimer = null;
    let snapshotTimer = null;
    function readSessionState() {
        const fallback = {
            session_uuid: createUuid(),
            session_type: "web",
            page_name: config.pageName,
            page_path: config.pagePath,
            page_url: config.pageUrl,
            source: config.source,
            status: "active",
            timeout_seconds: Math.max(1, Math.round(config.sessionTimeoutMs / 1000)),
            started_at: nowIso(),
            last_seen_at: nowIso(),
        };
        const saved = safeParse(localStorage.getItem(config.sessionStorageKey), null);
        if (!saved) {
            return fallback;
        }
        const lastSeenAtMs = Date.parse(saved.last_seen_at ?? "");
        const expired = Number.isNaN(lastSeenAtMs) || Date.now() - lastSeenAtMs > config.sessionTimeoutMs;
        if (expired) {
            return fallback;
        }
        return {
            ...fallback,
            ...saved,
            session_uuid: saved.session_uuid ?? fallback.session_uuid,
            started_at: saved.started_at ?? fallback.started_at,
            status: "active",
            last_seen_at: nowIso(),
            page_name: config.pageName,
            page_path: config.pagePath,
            page_url: config.pageUrl,
            source: config.source,
            timeout_seconds: Math.max(1, Math.round(config.sessionTimeoutMs / 1000)),
        };
    }
    let sessionState = readSessionState();
    function attachSessionField() {
        if (!config.sessionFieldId) {
            return;
        }
        const field = document.getElementById(config.sessionFieldId);
        if (field) {
            field.value = sessionState.session_uuid;
        }
    }
    function persistSession() {
        sessionState = {
            ...sessionState,
            page_name: config.pageName,
            page_path: config.pagePath,
            page_url: config.pageUrl,
            source: config.source,
            timeout_seconds: Math.max(1, Math.round(config.sessionTimeoutMs / 1000)),
        };
        localStorage.setItem(config.sessionStorageKey, JSON.stringify(sessionState));
        attachSessionField();
    }
    function touchSession(status = "active", endedAt) {
        sessionState.last_seen_at = nowIso();
        sessionState.status = status;
        if (endedAt) {
            sessionState.ended_at = endedAt;
        }
        else {
            delete sessionState.ended_at;
        }
        persistSession();
    }
    function readQueue() {
        const queue = safeParse(localStorage.getItem(config.queueStorageKey), []);
        return Array.isArray(queue) ? queue : [];
    }
    function writeQueue(queue) {
        localStorage.setItem(config.queueStorageKey, JSON.stringify(queue.slice(-config.maxQueuedEvents)));
    }
    function buildSessionPayload() {
        return {
            ...sessionState,
            page_name: config.pageName,
            page_path: config.pagePath,
            page_url: config.pageUrl,
            source: config.source,
            timeout_seconds: Math.max(1, Math.round(config.sessionTimeoutMs / 1000)),
            last_seen_at: sessionState.last_seen_at || nowIso(),
        };
    }
    async function sendWithFetch(payload) {
        const response = await fetch(config.collectUrl, {
            method: "POST",
            headers: { Accept: "application/json", "Content-Type": "application/json" },
            body: JSON.stringify(payload),
            keepalive: true,
        });
        if (!response.ok) {
            throw new Error(`Telemetry HTTP ${response.status}`);
        }
        return response.json();
    }
    function sendWithBeacon(payload) {
        if (!navigator.sendBeacon) {
            return false;
        }
        const body = new Blob([JSON.stringify(payload)], { type: "application/json" });
        return navigator.sendBeacon(config.collectUrl, body);
    }
    async function flushQueue(forceSessionSync = false, preferBeacon = false) {
        if (flushInFlight && !preferBeacon) {
            return false;
        }
        const queue = readQueue();
        const events = queue.slice(0, config.batchSize);
        if (events.length === 0 && !forceSessionSync) {
            return true;
        }
        const payload = { session: buildSessionPayload(), events };
        if (preferBeacon) {
            return sendWithBeacon(payload);
        }
        flushInFlight = true;
        try {
            await sendWithFetch(payload);
            if (events.length > 0) {
                const sentIds = new Set(events.map((event) => String(event.event_uuid)));
                writeQueue(readQueue().filter((event) => !sentIds.has(String(event.event_uuid))));
            }
            return true;
        }
        finally {
            flushInFlight = false;
        }
    }
    const debouncedFlush = debounce(() => {
        void flushQueue(false, false);
    }, 120);
    function track(eventType, message, payload = {}, level = "info", channel = "web_ui") {
        const event = {
            event_uuid: createUuid(),
            channel,
            event_type: eventType,
            level,
            source: config.source,
            message,
            payload,
            occurred_at: nowIso(),
        };
        const queue = readQueue();
        queue.push(event);
        writeQueue(queue);
        touchSession("active");
        debouncedFlush();
    }
    async function fetchSnapshot() {
        if (!config.snapshotUrl) {
            return null;
        }
        const url = `${config.snapshotUrl}?session_uuid=${encodeURIComponent(sessionState.session_uuid)}&limit=` +
            encodeURIComponent(String(config.snapshotLimit));
        const response = await fetch(url, {
            method: "GET",
            headers: { Accept: "application/json" },
            cache: "no-store",
        });
        if (!response.ok) {
            throw new Error(`Snapshot HTTP ${response.status}`);
        }
        const snapshot = await response.json();
        options.onSnapshot?.(snapshot, { ...sessionState });
        return snapshot;
    }
    function init() {
        attachSessionField();
        track("web.page_loaded", "La pagina abrio una sesion de telemetria.", {
            page_name: config.pageName,
            page_path: config.pagePath,
        });
        void flushQueue(true, false);
        window.addEventListener("focus", () => {
            track("web.window_focus", "La ventana recibio foco.");
        });
        window.addEventListener("pageshow", () => {
            track("web.page_visible", "La pagina volvio a mostrarse.");
        });
        document.addEventListener("visibilitychange", () => {
            if (document.visibilityState === "hidden") {
                track("web.page_hidden", "La pagina quedo oculta.");
                touchSession("paused");
                void flushQueue(false, true);
                return;
            }
            touchSession("active");
            track("web.page_visible", "La pagina esta visible.");
            void flushQueue(false, false);
        });
        window.addEventListener("pagehide", () => {
            track("web.pagehide", "La pagina se esta cerrando o navegando.");
            touchSession("paused", nowIso());
            void flushQueue(false, true);
        });
        window.addEventListener("beforeunload", () => {
            touchSession("paused", nowIso());
            void flushQueue(false, true);
        });
        heartbeatTimer = window.setInterval(() => {
            touchSession("active");
            void flushQueue(true, false);
        }, config.heartbeatMs);
        if (config.snapshotUrl) {
            snapshotTimer = window.setInterval(() => {
                void fetchSnapshot().catch((error) => options.onSnapshotError?.(error));
            }, config.snapshotMs);
            void fetchSnapshot().catch((error) => options.onSnapshotError?.(error));
        }
    }
    return {
        init,
        track,
        fetchSnapshot,
        getSessionState: () => ({ ...sessionState }),
        destroy: () => {
            if (heartbeatTimer !== null) {
                window.clearInterval(heartbeatTimer);
            }
            if (snapshotTimer !== null) {
                window.clearInterval(snapshotTimer);
            }
        },
    };
}
