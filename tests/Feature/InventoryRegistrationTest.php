<?php

namespace Tests\Feature;

use App\Models\Camara;
use App\Models\Estudiante;
use App\Models\HardwareTelemetryEvent;
use App\Models\HardwareTelemetrySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class InventoryRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_tag_redirects_to_register()
    {
        $response = $this->post(route('inventory.scan'), [
            'nfc_id' => 'TEST_UNK_TAG',
        ]);

        $response->assertRedirect(route('inventory.register', ['nfc_id' => 'TEST_UNK_TAG']));
    }

    public function test_register_page_shows_nfc_id()
    {
        $response = $this->get(route('inventory.register', ['nfc_id' => 'TEST_UNK_TAG']));

        $response->assertStatus(200);
        $response->assertSee('TEST_UNK_TAG');
        $response->assertSee('Asignar como estudiante');
        $response->assertSee('Asignar como camara');
        $response->assertSee('Crear estudiante y asignar tag');
        $response->assertSee('Crear camara y asignar tag');
    }

    public function test_can_register_new_student()
    {
        $estudiante = Estudiante::create([
            'nombre' => 'Test Student Name 123',
            'nfc_id' => null,
        ]);

        $response = $this->post(route('inventory.storeStudent'), [
            'nfc_id' => 'TEST_UNK_TAG',
            'estudiante_id' => $estudiante->id,
            'alias' => 'Monitor',
        ]);

        $response->assertRedirect(route('inventory.index'));
        $this->assertDatabaseHas('estudiantes', [
            'id' => $estudiante->id,
            'nfc_id' => 'TEST_UNK_TAG',
            'nombre' => 'Test Student Name 123',
            'alias' => 'Monitor',
        ]);

        // Assert session has user logged in
        $response->assertSessionHas('estudiante_actual');
    }

    public function test_can_register_new_camera()
    {
        $camara = Camara::create([
            'modelo' => 'Test Camera Model X',
            'nfc_id' => null,
            'estado' => 'Disponible',
        ]);

        $response = $this->post(route('inventory.storeCamera'), [
            'nfc_id' => 'TEST_UNK_TAG_CAM',
            'camara_id' => $camara->id,
            'alias' => 'Body 01',
        ]);

        $response->assertRedirect(route('inventory.index'));
        $this->assertDatabaseHas('camaras', [
            'id' => $camara->id,
            'nfc_id' => 'TEST_UNK_TAG_CAM',
            'modelo' => 'Test Camera Model X',
            'alias' => 'Body 01',
        ]);
    }

    public function test_can_create_new_student_from_unknown_tag_registration()
    {
        $response = $this->post(route('inventory.storeNewStudent'), [
            'nfc_id' => 'NEW_STUDENT_TAG',
            'nombre' => 'Laura Martinez',
            'alias' => 'Monitor',
        ]);

        $response->assertRedirect(route('inventory.index'));
        $this->assertDatabaseHas('estudiantes', [
            'nfc_id' => 'NEW_STUDENT_TAG',
            'nombre' => 'Laura Martinez',
            'alias' => 'Monitor',
            'activo' => true,
        ]);
        $response->assertSessionHas('estudiante_actual');
    }

    public function test_can_create_new_camera_from_unknown_tag_registration()
    {
        $response = $this->post(route('inventory.storeNewCamera'), [
            'nfc_id' => 'NEW_CAMERA_TAG',
            'modelo' => 'Canon T7 - Kit A',
            'alias' => 'Body A',
            'estado' => 'Disponible',
        ]);

        $response->assertRedirect(route('inventory.index'));
        $this->assertDatabaseHas('camaras', [
            'nfc_id' => 'NEW_CAMERA_TAG',
            'modelo' => 'Canon T7 - Kit A',
            'alias' => 'Body A',
            'estado' => 'Disponible',
        ]);
    }

    public function test_student_registration_rejects_tag_already_used_by_camera()
    {
        Camara::create([
            'modelo' => 'Shared Tag Camera',
            'nfc_id' => 'DUPLICATE_TAG',
            'estado' => 'Disponible',
        ]);

        $estudiante = Estudiante::create([
            'nombre' => 'Pending Student',
            'nfc_id' => null,
        ]);

        $response = $this->from(route('inventory.register', ['nfc_id' => 'DUPLICATE_TAG']))
            ->post(route('inventory.storeStudent'), [
                'nfc_id' => 'DUPLICATE_TAG',
                'estudiante_id' => $estudiante->id,
                'alias' => 'Nope',
            ]);

        $response->assertRedirect(route('inventory.register', ['nfc_id' => 'DUPLICATE_TAG']));
        $response->assertSessionHasErrors('nfc_id');
    }

    public function test_unknown_rfid_tag_returns_absolute_and_relative_register_links()
    {
        $response = $this->postJson(route('inventory.scan.rfid'), [
            'uid' => 'RFID_TAG_404',
            'source' => 'usb-local-1',
        ]);

        $response->assertStatus(404);
        $this->assertStringEndsWith(
            '/inventory/register/RFID_TAG_404',
            $response->json('register_url')
        );
        $response->assertJsonPath('register_path', '/inventory/register/RFID_TAG_404');
    }

    public function test_web_telemetry_collect_creates_session_and_event()
    {
        $response = $this->postJson(route('inventory.telemetry.collect'), [
            'session' => [
                'session_uuid' => 'web-session-123',
                'session_type' => 'web',
                'source' => 'inventory-web',
                'page_name' => 'inventory',
                'page_path' => '/inventory',
                'page_url' => 'http://localhost/inventory',
                'status' => 'active',
                'timeout_seconds' => 60,
                'started_at' => now()->subSeconds(5)->toIso8601String(),
                'last_seen_at' => now()->toIso8601String(),
            ],
            'events' => [
                [
                    'event_uuid' => 'event-web-123',
                    'channel' => 'web_ui',
                    'event_type' => 'web.page_loaded',
                    'level' => 'info',
                    'message' => 'Pagina abierta',
                    'occurred_at' => now()->toIso8601String(),
                    'payload' => [
                        'page_name' => 'inventory',
                    ],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('session_uuid', 'web-session-123');

        $this->assertDatabaseHas('hardware_telemetry_sessions', [
            'session_uuid' => 'web-session-123',
            'session_type' => 'web',
            'source' => 'inventory-web',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('hardware_telemetry_events', [
            'event_uuid' => 'event-web-123',
            'session_uuid' => 'web-session-123',
            'event_type' => 'web.page_loaded',
            'channel' => 'web_ui',
        ]);
    }

    public function test_web_telemetry_session_reopens_cleanly_after_short_reload()
    {
        $endedAt = now()->subSeconds(2)->toIso8601String();
        $this->postJson(route('inventory.telemetry.collect'), [
            'session' => [
                'session_uuid' => 'web-session-reload',
                'session_type' => 'web',
                'source' => 'inventory-web',
                'status' => 'paused',
                'started_at' => now()->subSeconds(30)->toIso8601String(),
                'last_seen_at' => now()->subSeconds(2)->toIso8601String(),
                'ended_at' => $endedAt,
            ],
            'events' => [],
        ])->assertOk();

        $this->postJson(route('inventory.telemetry.collect'), [
            'session' => [
                'session_uuid' => 'web-session-reload',
                'session_type' => 'web',
                'source' => 'inventory-web',
                'status' => 'active',
                'started_at' => now()->subSeconds(30)->toIso8601String(),
                'last_seen_at' => now()->toIso8601String(),
            ],
            'events' => [],
        ])->assertOk();

        $session = HardwareTelemetrySession::where('session_uuid', 'web-session-reload')->first();

        $this->assertNotNull($session);
        $this->assertSame('active', $session->status);
        $this->assertNull($session->ended_at);
    }

    public function test_rfid_scan_records_bridge_session_and_backend_event()
    {
        $response = $this->postJson(route('inventory.scan.rfid'), [
            'uid' => 'RFID_TAG_BRIDGE_404',
            'source' => 'usb-reader-lab',
            'reader_model' => 'pn532',
            'bridge_session_uuid' => 'bridge-session-001',
            'bridge_transport' => 'keyboard',
            'bridge_mode' => 'api',
            'bridge_host' => 'LAB-PC',
            'bridge_pid' => 999,
        ]);

        $response->assertStatus(404);

        $this->assertDatabaseHas('hardware_telemetry_sessions', [
            'session_uuid' => 'bridge-session-001',
            'session_type' => 'bridge',
            'source' => 'usb-reader-lab',
        ]);

        $bridgeSession = HardwareTelemetrySession::where('session_uuid', 'bridge-session-001')->first();
        $this->assertNotNull($bridgeSession);
        $this->assertSame('pn532', $bridgeSession->metadata['reader_model'] ?? null);

        $event = HardwareTelemetryEvent::where('session_uuid', 'bridge-session-001')
            ->where('event_type', 'backend.rfid_scan.unregistered')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('usb-reader-lab', $event->source);
        $this->assertSame('bridge', $event->session_type);
    }

    public function test_auto_shutdown_watchdog_does_not_shutdown_when_recent_activity_exists()
    {
        Process::fake();

        HardwareTelemetrySession::create([
            'session_uuid' => 'watchdog-session-active',
            'session_type' => 'web',
            'source' => 'inventory-web',
            'status' => 'active',
            'started_at' => now()->subMinutes(2),
            'last_seen_at' => now()->subMinute(),
        ]);

        $this->artisan('device:auto-shutdown', [
            '--once' => true,
            '--idle-minutes' => 10,
            '--boot-grace-minutes' => 0,
            '--shutdown-command' => 'echo should-not-run',
        ])
            ->expectsOutputToContain('Actividad reciente detectada.')
            ->assertSuccessful();

        Process::assertNothingRan();
    }

    public function test_auto_shutdown_watchdog_runs_shutdown_command_after_idle_timeout()
    {
        Process::fake();

        HardwareTelemetryEvent::create([
            'event_uuid' => 'watchdog-idle-event',
            'session_uuid' => 'watchdog-session-idle',
            'session_type' => 'bridge',
            'channel' => 'backend',
            'event_type' => 'backend.rfid_scan.received',
            'level' => 'info',
            'source' => 'usb-reader',
            'message' => 'Ultima actividad vieja',
            'occurred_at' => now()->subMinutes(45),
        ]);

        $this->artisan('device:auto-shutdown', [
            '--once' => true,
            '--idle-minutes' => 10,
            '--boot-grace-minutes' => 0,
            '--shutdown-command' => 'echo simulated-shutdown',
        ])
            ->expectsOutputToContain('Dispositivo inactivo por')
            ->assertSuccessful();

        Process::assertRan('echo simulated-shutdown');
    }
}

