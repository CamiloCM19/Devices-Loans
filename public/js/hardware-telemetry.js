(function () {
    if (window.HardwareTelemetryPage) {
        return;
    }

    const DEFAULTS = {
        sessionStorageKey: 'hardware-telemetry-session-v1',
        queueStorageKey: 'hardware-telemetry-queue-v1',
        sessionTimeoutMs: 60 * 1000,
        heartbeatMs: 15 * 1000,
        snapshotMs: 4 * 1000,
        snapshotLimit: 20,
        batchSize: 20,
        maxQueuedEvents: 200,
        collectUrl: null,
        snapshotUrl: null,
        pageName: 'inventory',
        pagePath: window.location.pathname,
        pageUrl: window.location.href,
        source: 'inventory-web',
        sessionFieldId: null,
        onSnapshot: null,
        onSnapshotError: null,
    };

    function safeParse(json, fallbackValue) {
        if (!json) {
            return fallbackValue;
        }

        try {
            const parsed = JSON.parse(json);
            return parsed == null ? fallbackValue : parsed;
        } catch (error) {
            return fallbackValue;
        }
    }

    function nowIso() {
        return new Date().toISOString();
    }

    function createUuid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }

        const template = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx';
        return template.replace(/[xy]/g, function (char) {
            const random = Math.random() * 16 | 0;
            const value = char === 'x' ? random : (random & 0x3 | 0x8);
            return value.toString(16);
        });
    }

    function debounce(fn, delayMs) {
        let timer = null;

        return function debounced() {
            const args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(null, args);
            }, delayMs);
        };
    }

    window.HardwareTelemetryPage = function createHardwareTelemetryPage(options) {
        const config = Object.assign({}, DEFAULTS, options || {});
        let flushInFlight = false;
        let destroyed = false;
        let heartbeatTimer = null;
        let snapshotTimer = null;
        const cleanupFns = [];

        function readSessionState() {
            const fallback = {
                session_uuid: createUuid(),
                session_type: 'web',
                page_name: config.pageName,
                page_path: config.pagePath,
                page_url: config.pageUrl,
                source: config.source,
                status: 'active',
                timeout_seconds: Math.max(1, Math.round(config.sessionTimeoutMs / 1000)),
                started_at: nowIso(),
                last_seen_at: nowIso(),
            };

            const saved = safeParse(window.localStorage.getItem(config.sessionStorageKey), null);
            if (!saved || typeof saved !== 'object') {
                return fallback;
            }

            const lastSeenAtMs = Date.parse(saved.last_seen_at || '');
            const expired = Number.isNaN(lastSeenAtMs) || (Date.now() - lastSeenAtMs) > config.sessionTimeoutMs;
            if (expired) {
                return fallback;
            }

            return Object.assign({}, fallback, saved, {
                session_uuid: saved.session_uuid || fallback.session_uuid,
                started_at: saved.started_at || fallback.started_at,
                status: 'active',
                last_seen_at: nowIso(),
                page_name: config.pageName,
                page_path: config.pagePath,
                page_url: config.pageUrl,
                source: config.source,
                timeout_seconds: Math.max(1, Math.round(config.sessionTimeoutMs / 1000)),
            });
        }

        let sessionState = readSessionState();

        function persistSession() {
            if (destroyed) {
                return;
            }

            sessionState.page_name = config.pageName;
            sessionState.page_path = config.pagePath;
            sessionState.page_url = config.pageUrl;
            sessionState.source = config.source;
            sessionState.timeout_seconds = Math.max(1, Math.round(config.sessionTimeoutMs / 1000));
            window.localStorage.setItem(config.sessionStorageKey, JSON.stringify(sessionState));
            attachSessionField();
        }

        function buildSessionPayload(overrides) {
            const payload = Object.assign({}, sessionState, overrides || {});
            payload.page_name = config.pageName;
            payload.page_path = config.pagePath;
            payload.page_url = config.pageUrl;
            payload.source = config.source;
            payload.timeout_seconds = Math.max(1, Math.round(config.sessionTimeoutMs / 1000));
            payload.last_seen_at = payload.last_seen_at || nowIso();
            return payload;
        }

        function attachSessionField() {
            if (!config.sessionFieldId) {
                return;
            }

            const field = document.getElementById(config.sessionFieldId);
            if (field) {
                field.value = sessionState.session_uuid;
            }
        }

        function touchSession(status, endedAt) {
            sessionState.last_seen_at = nowIso();
            sessionState.status = status || 'active';
            if (endedAt) {
                sessionState.ended_at = endedAt;
            } else {
                delete sessionState.ended_at;
            }
            persistSession();
        }

        function readQueue() {
            const queue = safeParse(window.localStorage.getItem(config.queueStorageKey), []);
            return Array.isArray(queue) ? queue : [];
        }

        function writeQueue(queue) {
            const pruned = Array.isArray(queue)
                ? queue.slice(-config.maxQueuedEvents)
                : [];
            window.localStorage.setItem(config.queueStorageKey, JSON.stringify(pruned));
        }

        function queueEvent(eventType, message, payload, level, channel) {
            const event = {
                event_uuid: createUuid(),
                channel: channel || 'web_ui',
                event_type: eventType,
                level: level || 'info',
                source: config.source,
                message: message || null,
                payload: payload || {},
                occurred_at: nowIso(),
            };

            const queue = readQueue();
            queue.push(event);
            writeQueue(queue);
            touchSession('active');

            return event;
        }

        async function sendWithFetch(payload) {
            const response = await fetch(config.collectUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
                keepalive: true,
            });

            if (!response.ok) {
                throw new Error('Telemetry HTTP ' + response.status);
            }

            return response.json();
        }

        function sendWithBeacon(payload) {
            if (!navigator.sendBeacon) {
                return false;
            }

            const body = new Blob([JSON.stringify(payload)], {
                type: 'application/json',
            });

            return navigator.sendBeacon(config.collectUrl, body);
        }

        async function flushQueue(forceSessionSync, preferBeacon) {
            if (destroyed || !config.collectUrl) {
                return false;
            }

            if (flushInFlight && !preferBeacon) {
                return false;
            }

            const queue = readQueue();
            const events = queue.slice(0, config.batchSize);
            if (events.length === 0 && !forceSessionSync) {
                return true;
            }

            const payload = {
                session: buildSessionPayload(),
                events: events,
            };

            if (preferBeacon) {
                return sendWithBeacon(payload);
            }

            flushInFlight = true;
            try {
                await sendWithFetch(payload);
                if (events.length > 0) {
                    const sentIds = {};
                    events.forEach(function (event) {
                        sentIds[event.event_uuid] = true;
                    });
                    const remaining = readQueue().filter(function (event) {
                        return !sentIds[event.event_uuid];
                    });
                    writeQueue(remaining);
                    if (remaining.length > 0) {
                        window.setTimeout(function () {
                            flushQueue(false, false);
                        }, 50);
                    }
                }
                return true;
            } finally {
                flushInFlight = false;
            }
        }

        const debouncedFlush = debounce(function () {
            flushQueue(false, false).catch(function () {
                return null;
            });
        }, 120);

        function track(eventType, message, payload, level, channel) {
            const event = queueEvent(eventType, message, payload, level, channel);
            debouncedFlush();
            return event;
        }

        function syncSession(status) {
            touchSession(status || 'active');
            flushQueue(true, false).catch(function () {
                return null;
            });
        }

        async function fetchSnapshot() {
            if (destroyed || !config.snapshotUrl) {
                return null;
            }

            const url = config.snapshotUrl
                + '?session_uuid=' + encodeURIComponent(sessionState.session_uuid)
                + '&limit=' + encodeURIComponent(String(config.snapshotLimit));

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                },
                cache: 'no-store',
            });

            if (!response.ok) {
                throw new Error('Snapshot HTTP ' + response.status);
            }

            const snapshot = await response.json();
            if (typeof config.onSnapshot === 'function') {
                config.onSnapshot(snapshot, Object.assign({}, sessionState));
            }
            return snapshot;
        }

        function addListener(target, eventName, handler, options) {
            target.addEventListener(eventName, handler, options);
            cleanupFns.push(function () {
                target.removeEventListener(eventName, handler, options);
            });
        }

        function init() {
            attachSessionField();
            syncSession('active');

            addListener(window, 'focus', function () {
                touchSession('active');
            });

            addListener(document, 'visibilitychange', function () {
                if (document.visibilityState === 'hidden') {
                    touchSession('paused');
                    flushQueue(false, true);
                    return;
                }

                touchSession('active');
                flushQueue(false, false).catch(function () {
                    return null;
                });
            });

            addListener(window, 'pagehide', function () {
                touchSession('paused', nowIso());
                flushQueue(false, true);
            });

            addListener(window, 'beforeunload', function () {
                touchSession('paused', nowIso());
                flushQueue(false, true);
            });

            heartbeatTimer = window.setInterval(function () {
                syncSession('active');
            }, config.heartbeatMs);

            if (config.snapshotUrl) {
                snapshotTimer = window.setInterval(function () {
                    fetchSnapshot().catch(function (error) {
                        if (typeof config.onSnapshotError === 'function') {
                            config.onSnapshotError(error);
                        }
                    });
                }, config.snapshotMs);

                fetchSnapshot().catch(function (error) {
                    if (typeof config.onSnapshotError === 'function') {
                        config.onSnapshotError(error);
                    }
                });
            }

            return {
                session_uuid: sessionState.session_uuid,
            };
        }

        function destroy() {
            destroyed = true;
            cleanupFns.splice(0).forEach(function (cleanup) {
                cleanup();
            });
            if (heartbeatTimer) {
                window.clearInterval(heartbeatTimer);
            }
            if (snapshotTimer) {
                window.clearInterval(snapshotTimer);
            }
        }

        return {
            init: init,
            destroy: destroy,
            track: track,
            syncSession: syncSession,
            flushQueue: flushQueue,
            fetchSnapshot: fetchSnapshot,
            attachSessionField: attachSessionField,
            getSessionState: function () {
                return Object.assign({}, sessionState);
            },
        };
    };
})();
