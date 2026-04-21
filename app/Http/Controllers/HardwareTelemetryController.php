<?php

namespace App\Http\Controllers;

use App\Support\HardwareTelemetryRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\HardwareTelemetrySession;
use Illuminate\View\View;

class HardwareTelemetryController extends Controller
{
    public function __construct(
        private readonly HardwareTelemetryRecorder $telemetry,
    ) {
    }

    public function collect(Request $request): JsonResponse
    {
        $session = is_array($request->input('session')) ? $request->input('session') : [];
        $events = is_array($request->input('events')) ? $request->input('events') : [];

        $sessionUuid = trim((string) ($session['session_uuid'] ?? ''));
        if ($sessionUuid === '') {
            return response()->json([
                'ok' => false,
                'message' => 'session_uuid es obligatorio.',
            ], 422);
        }

        $storedSession = $this->telemetry->touchSession([
            'session_uuid' => $sessionUuid,
            'session_type' => trim((string) ($session['session_type'] ?? 'web')) ?: 'web',
            'source' => $session['source'] ?? 'inventory-web',
            'page_name' => $session['page_name'] ?? 'inventory',
            'page_path' => $session['page_path'] ?? $request->path(),
            'page_url' => null,
            'status' => $session['status'] ?? 'active',
            'timeout_seconds' => $session['timeout_seconds'] ?? 60,
            'user_agent' => null,
            'metadata' => $session['metadata'] ?? [],
            'started_at' => $session['started_at'] ?? null,
            'last_seen_at' => $session['last_seen_at'] ?? now()->toIso8601String(),
            'ended_at' => $session['ended_at'] ?? null,
        ]);

        $storedEvents = [];
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            if (!$this->shouldPersistEvent($event)) {
                continue;
            }

            $storedEvents[] = $this->telemetry->recordEvent([
                'event_uuid' => $event['event_uuid'] ?? null,
                'session_uuid' => $storedSession?->session_uuid,
                'session_type' => $storedSession?->session_type ?? 'web',
                'channel' => $event['channel'] ?? 'web_ui',
                'event_type' => $event['event_type'] ?? 'web.event',
                'level' => $event['level'] ?? 'info',
                'source' => $event['source'] ?? $storedSession?->source,
                'uid' => $event['uid'] ?? null,
                'message' => $event['message'] ?? null,
                'payload' => $event['payload'] ?? [],
                'occurred_at' => $event['occurred_at'] ?? now()->toIso8601String(),
                'page_name' => $storedSession?->page_name,
                'page_path' => $storedSession?->page_path,
                'page_url' => $storedSession?->page_url,
                'user_agent' => $storedSession?->user_agent,
                'timeout_seconds' => $storedSession?->timeout_seconds ?? 60,
            ])->event_uuid;
        }

        return response()->json([
            'ok' => true,
            'session_uuid' => $storedSession?->session_uuid,
            'stored_events' => $storedEvents,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    private function shouldPersistEvent(array $event): bool
    {
        $channel = trim((string) ($event['channel'] ?? ''));
        $eventType = trim((string) ($event['event_type'] ?? ''));
        $eventUuid = trim((string) ($event['event_uuid'] ?? ''));

        if ($eventUuid !== '') {
            return true;
        }

        if ($channel === 'web_ui') {
            return false;
        }

        return !str_starts_with($eventType, 'web.');
    }

    public function index(): View
    {
        $latestBridgeSession = HardwareTelemetrySession::query()
            ->where('session_type', 'bridge')
            ->orderByDesc('last_seen_at')
            ->first();

        return view('telemetry', [
            'latestBridgeSession' => $latestBridgeSession,
        ]);
    }

    public function snapshot(Request $request): JsonResponse
    {
        return response()->json(
            $this->telemetry->snapshot(
                trim((string) $request->query('session_uuid', '')),
                (int) $request->query('limit', 20),
            )
        );
    }
}
