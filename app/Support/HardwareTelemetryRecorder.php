<?php

namespace App\Support;

use App\Models\Estudiante;
use App\Models\HardwareTelemetryEvent;
use App\Models\HardwareTelemetrySession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class HardwareTelemetryRecorder
{
    public function touchSession(array $data): ?HardwareTelemetrySession
    {
        $sessionUuid = trim((string) ($data['session_uuid'] ?? ''));
        if ($sessionUuid === '') {
            return null;
        }

        $session = HardwareTelemetrySession::firstOrNew([
            'session_uuid' => $sessionUuid,
        ]);

        $metadata = $session->metadata ?? [];
        $incomingMetadata = $this->normalizeArray($data['metadata'] ?? []);
        if ($incomingMetadata !== []) {
            $metadata = array_replace_recursive($metadata, $incomingMetadata);
        }

        $startedAt = $this->parseTimestamp($data['started_at'] ?? null) ?? ($session->started_at ?? now());
        $lastSeenAt = $this->parseTimestamp($data['last_seen_at'] ?? null) ?? now();
        $status = $this->nullableString($data['status'] ?? $session->status ?? 'active') ?? 'active';
        $endedAt = array_key_exists('ended_at', $data)
            ? $this->parseTimestamp($data['ended_at'])
            : ($status === 'active' ? null : $session->ended_at);

        $session->fill([
            'session_type' => trim((string) ($data['session_type'] ?? $session->session_type ?? 'web')) ?: 'web',
            'source' => $this->nullableString($data['source'] ?? $session->source),
            'page_name' => $this->nullableString($data['page_name'] ?? $session->page_name),
            'page_path' => $this->nullableString($data['page_path'] ?? $session->page_path),
            'page_url' => $this->nullableString($data['page_url'] ?? $session->page_url),
            'status' => $status,
            'timeout_seconds' => max(1, (int) ($data['timeout_seconds'] ?? $session->timeout_seconds ?? 60)),
            'user_agent' => $this->nullableString($data['user_agent'] ?? $session->user_agent),
            'metadata' => $metadata === [] ? null : $metadata,
            'started_at' => $startedAt,
            'last_seen_at' => $lastSeenAt,
            'ended_at' => $endedAt,
        ]);

        if ($session->ended_at !== null) {
            $session->status = 'closed';
        }

        $session->save();

        return $session;
    }

    public function recordEvent(array $data): HardwareTelemetryEvent
    {
        $session = null;
        $sessionUuid = trim((string) ($data['session_uuid'] ?? ''));
        if ($sessionUuid !== '') {
            $session = $this->touchSession([
                'session_uuid' => $sessionUuid,
                'session_type' => $data['session_type'] ?? 'web',
                'source' => $data['source'] ?? null,
                'page_name' => $data['page_name'] ?? null,
                'page_path' => $data['page_path'] ?? null,
                'page_url' => $data['page_url'] ?? null,
                'status' => $data['status'] ?? 'active',
                'timeout_seconds' => $data['timeout_seconds'] ?? 60,
                'user_agent' => $data['user_agent'] ?? null,
                'metadata' => $data['metadata'] ?? [],
                'started_at' => $data['session_started_at'] ?? null,
                'last_seen_at' => $data['occurred_at'] ?? $data['last_seen_at'] ?? null,
                'ended_at' => $data['session_ended_at'] ?? null,
            ]);
        }

        $eventUuid = trim((string) ($data['event_uuid'] ?? '')) ?: (string) Str::uuid();
        $event = HardwareTelemetryEvent::firstOrNew([
            'event_uuid' => $eventUuid,
        ]);

        $payload = $this->normalizeArray($data['payload'] ?? []);
        $uid = $this->nullableString(
            $data['uid']
            ?? ($payload['uid_normalized'] ?? null)
            ?? ($payload['uid'] ?? null)
        );

        $event->fill([
            'hardware_telemetry_session_id' => $session?->id,
            'session_uuid' => $session?->session_uuid ?? $this->nullableString($sessionUuid),
            'session_type' => $session?->session_type ?? $this->nullableString($data['session_type'] ?? null),
            'channel' => trim((string) ($data['channel'] ?? 'backend')) ?: 'backend',
            'event_type' => trim((string) ($data['event_type'] ?? 'telemetry.event')) ?: 'telemetry.event',
            'level' => trim((string) ($data['level'] ?? 'info')) ?: 'info',
            'source' => $this->nullableString($data['source'] ?? $session?->source),
            'uid' => $uid,
            'correlation_id' => $this->nullableString($data['correlation_id'] ?? null),
            'message' => $this->nullableString($data['message'] ?? null),
            'payload' => $payload === [] ? null : $payload,
            'occurred_at' => $this->parseTimestamp($data['occurred_at'] ?? null) ?? now(),
        ]);
        $event->save();

        return $event;
    }

    public function snapshot(?string $browserSessionUuid = null, int $recentLimit = 20): array
    {
        $browserSessionUuid = $this->nullableString($browserSessionUuid);
        $browserSession = $browserSessionUuid
            ? HardwareTelemetrySession::where('session_uuid', $browserSessionUuid)->first()
            : null;

        $recentEvents = HardwareTelemetryEvent::query()
            ->where(function ($query) {
                $query
                    ->where('session_type', 'bridge')
                    ->orWhere('event_type', 'like', 'backend.rfid_scan.%')
                    ->orWhere('event_type', 'like', 'bridge.%');
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(max(1, min($recentLimit, 50)))
            ->get()
            ->map(fn (HardwareTelemetryEvent $event) => $this->formatEvent($event))
            ->values();

        $latestReaderEvent = HardwareTelemetryEvent::query()
            ->where(function ($query) {
                $query
                    ->where('session_type', 'bridge')
                    ->orWhere('event_type', 'like', 'backend.rfid_scan.%')
                    ->orWhere('event_type', 'like', 'bridge.%');
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first();

        $latestBrowserEvent = $browserSessionUuid
            ? HardwareTelemetryEvent::query()
                ->where('session_uuid', $browserSessionUuid)
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->first()
            : null;

        $activeBridgeSessions = HardwareTelemetrySession::query()
            ->where('session_type', 'bridge')
            ->where('last_seen_at', '>=', now()->subMinutes(20))
            ->orderByDesc('last_seen_at')
            ->limit(5)
            ->get()
            ->map(function (HardwareTelemetrySession $session) {
                $studentId = $session->source
                    ? Cache::get('rfid:current_student:' . $session->source)
                    : null;
                $student = $studentId ? Estudiante::find($studentId) : null;

                return [
                    'session_uuid' => $session->session_uuid,
                    'source' => $session->source,
                    'reader_model' => $session->metadata['reader_model'] ?? null,
                    'status' => $session->status,
                    'started_at' => $session->started_at?->toIso8601String(),
                    'last_seen_at' => $session->last_seen_at?->toIso8601String(),
                    'active_student' => $student ? [
                        'id' => $student->id,
                        'nombre' => $student->nombre,
                    ] : null,
                ];
            })
            ->values();

        $bridgeLogEvents = collect($this->readBridgeLogTail(15))->map(function (array $event) {
            return [
                'timestamp' => $event['timestamp'] ?? null,
                'event_type' => $event['event_type'] ?? 'bridge.unknown',
                'level' => $event['level'] ?? 'info',
                'message' => $event['message'] ?? null,
                'source' => $event['source'] ?? null,
                'session_uuid' => $event['bridge_session_uuid'] ?? null,
                'payload' => $event['payload'] ?? [],
            ];
        })->values();

        $recentBridgeTimelineEvents = HardwareTelemetryEvent::query()
            ->where(function ($query) {
                $query
                    ->where('session_type', 'bridge')
                    ->orWhere('event_type', 'like', 'backend.rfid_scan.%')
                    ->orWhere('event_type', 'like', 'bridge.%');
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(function (HardwareTelemetryEvent $event) {
                return [
                    'timestamp' => $event->occurred_at?->toIso8601String(),
                    'event_type' => $event->event_type,
                    'level' => $event->level,
                    'message' => $event->message,
                    'source' => $event->source,
                    'session_uuid' => $event->session_uuid,
                    'payload' => $event->payload ?? [],
                ];
            })
            ->values();

        $bridgeLogEvents = $bridgeLogEvents
            ->concat($recentBridgeTimelineEvents)
            ->unique(function (array $event) {
                return implode('|', [
                    $event['timestamp'] ?? '',
                    $event['event_type'] ?? '',
                    $event['source'] ?? '',
                    json_encode($event['payload'] ?? []),
                ]);
            })
            ->sortBy(function (array $event) {
                return $event['timestamp'] ?? '';
            })
            ->values()
            ->take(-15)
            ->values();

        return [
            'browser_session' => $browserSession ? [
                'session_uuid' => $browserSession->session_uuid,
                'session_type' => $browserSession->session_type,
                'source' => $browserSession->source,
                'page_name' => $browserSession->page_name,
                'page_path' => $browserSession->page_path,
                'status' => $browserSession->status,
                'started_at' => $browserSession->started_at?->toIso8601String(),
                'last_seen_at' => $browserSession->last_seen_at?->toIso8601String(),
                'ended_at' => $browserSession->ended_at?->toIso8601String(),
            ] : null,
            'latest_reader_event' => $latestReaderEvent ? $this->formatEvent($latestReaderEvent) : null,
            'latest_browser_event' => $latestBrowserEvent ? $this->formatEvent($latestBrowserEvent) : null,
            'active_bridge_sessions' => $activeBridgeSessions,
            'recent_events' => $recentEvents,
            'bridge_log_events' => $bridgeLogEvents,
            'server_time' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function readBridgeLogTail(int $maxLines = 20): array
    {
        $path = storage_path('logs/rfid-bridge-telemetry.jsonl');
        if (!File::exists($path)) {
            return [];
        }

        $lines = File::lines($path)
            ->filter(fn (string $line) => trim($line) !== '')
            ->all();
        $lines = array_slice($lines, -max(1, min($maxLines, 100)));

        $events = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $events[] = $decoded;
            }
        }

        return $events;
    }

    private function formatEvent(HardwareTelemetryEvent $event): array
    {
        return [
            'id' => $event->id,
            'event_uuid' => $event->event_uuid,
            'session_uuid' => $event->session_uuid,
            'session_type' => $event->session_type,
            'channel' => $event->channel,
            'event_type' => $event->event_type,
            'level' => $event->level,
            'source' => $event->source,
            'uid' => $event->uid,
            'message' => $event->message,
            'payload' => $event->payload ?? [],
            'occurred_at' => $event->occurred_at?->toIso8601String(),
        ];
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
