<?php

namespace App\Services;

use App\Models\Camara;
use App\Models\Estudiante;
use App\Models\LogPrestamo;
use App\Support\HardwareTelemetryRecorder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RfidScanProcessor
{
    public function __construct(
        private readonly HardwareTelemetryRecorder $telemetry,
    ) {
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{body: array<string, mixed>, status_code: int}
     */
    public function process(?string $rawUid, array $context = []): array
    {
        $input = $this->normalizeNfcId($rawUid);
        $configuredSigningKey = (string) env('RFID_READER_SIGNING_KEY', '');
        $configuredToken = (string) env('RFID_READER_TOKEN', '');
        $providedToken = (string) ($context['token'] ?? '');
        $source = trim((string) ($context['source'] ?? 'nfc-reader'));
        if ($source === '') {
            $source = 'nfc-reader';
        }

        $bridgeSessionUuid = trim((string) ($context['bridge_session_uuid'] ?? ''));
        $bridgeMetadata = [
            'bridge_transport' => $context['bridge_transport'] ?? 'serial',
            'bridge_mode' => $context['bridge_mode'] ?? 'direct',
            'bridge_host' => $context['bridge_host'] ?? null,
            'bridge_pid' => $context['bridge_pid'] ?? null,
            'reader_model' => $context['reader_model'] ?? (env('RFID_READER_DRIVER', env('RFID_READER_MODEL')) ?: null),
        ];

        $this->ensureBridgeSession($bridgeSessionUuid, $source, $bridgeMetadata);

        if ($configuredSigningKey !== '') {
            $authError = $this->validateSignedReaderRequest($configuredSigningKey, $input, $source, $context);
            if ($authError !== null) {
                $this->recordBridgeTelemetryEvent(
                    $bridgeSessionUuid,
                    $source,
                    'backend.rfid_scan.unauthorized',
                    'Se rechazo una lectura RFID por firma dinamica invalida.',
                    [
                        'ip' => $context['ip'] ?? null,
                        'reason' => $authError,
                    ],
                    'warning',
                    $bridgeMetadata
                );

                return [
                    'body' => [
                        'ok' => false,
                        'status' => 'unauthorized',
                        'message' => 'Firma RFID invalida.',
                    ],
                    'status_code' => 401,
                ];
            }
        } elseif ($configuredToken !== '' && !hash_equals($configuredToken, $providedToken)) {
            $this->recordBridgeTelemetryEvent(
                $bridgeSessionUuid,
                $source,
                'backend.rfid_scan.unauthorized',
                'Se rechazo una lectura RFID por token invalido.',
                [
                    'ip' => $context['ip'] ?? null,
                ],
                'warning',
                $bridgeMetadata
            );

            return [
                'body' => [
                    'ok' => false,
                    'status' => 'unauthorized',
                    'message' => 'Token invalido.',
                ],
                'status_code' => 401,
            ];
        }

        Log::info('rfid_scan_received', [
            'source' => $source,
            'uid_raw' => $rawUid,
            'uid_normalized' => $input,
            'ip' => $context['ip'] ?? 'cli',
        ]);

        $this->recordBridgeTelemetryEvent(
            $bridgeSessionUuid,
            $source,
            'backend.rfid_scan.received',
            'El backend recibio una lectura RFID desde el lector NFC/RFID.',
            [
                'uid_raw' => $rawUid,
                'uid_normalized' => $input,
                'ip' => $context['ip'] ?? 'cli',
            ],
            'info',
            $bridgeMetadata
        );

        if ($input === '') {
            $this->recordBridgeTelemetryEvent(
                $bridgeSessionUuid,
                $source,
                'backend.rfid_scan.invalid_input',
                'El lector NFC/RFID envio un UID vacio o invalido.',
                [
                    'uid_raw' => $rawUid,
                    'uid_normalized' => $input,
                ],
                'warning',
                $bridgeMetadata
            );

            return [
                'body' => [
                    'ok' => false,
                    'status' => 'invalid_input',
                    'message' => 'UID vacio o invalido.',
                ],
                'status_code' => 422,
            ];
        }

        $contextKey = 'rfid:current_student:' . $source;

        $estudiante = $this->findEstudianteByNfc($input);
        if ($estudiante) {
            Cache::put($contextKey, $estudiante->id, now()->addMinutes(20));
            $this->recordBridgeTelemetryEvent(
                $bridgeSessionUuid,
                $source,
                'backend.rfid_scan.student_ok',
                "Se reconocio al estudiante {$estudiante->nombre} desde el lector NFC/RFID.",
                [
                    'uid_normalized' => $input,
                    'student_id' => $estudiante->id,
                    'student_name' => $estudiante->nombre,
                    'context_key' => $contextKey,
                ],
                'info',
                $bridgeMetadata
            );

            return [
                'body' => [
                    'ok' => true,
                    'status' => 'student_ok',
                    'message' => "Hola {$estudiante->nombre}, escanea una camara.",
                    'student' => [
                        'id' => $estudiante->id,
                        'nombre' => $estudiante->nombre,
                    ],
                ],
                'status_code' => 200,
            ];
        }

        $camara = $this->findCamaraByNfc($input);
        if ($camara) {
            $estudianteId = Cache::get($contextKey);
            if (!$estudianteId) {
                $this->recordBridgeTelemetryEvent(
                    $bridgeSessionUuid,
                    $source,
                    'backend.rfid_scan.student_required',
                    'Se detecto una camara por RFID, pero no habia estudiante activo en cache.',
                    [
                        'uid_normalized' => $input,
                        'camera_id' => $camara->id,
                        'camera_model' => $camara->modelo,
                        'context_key' => $contextKey,
                    ],
                    'warning',
                    $bridgeMetadata
                );

                return [
                    'body' => [
                        'ok' => false,
                        'status' => 'student_required',
                        'message' => 'Escanea primero el carnet del estudiante.',
                    ],
                    'status_code' => 409,
                ];
            }

            $estudianteActual = Estudiante::find($estudianteId);
            if (!$estudianteActual) {
                Cache::forget($contextKey);
                $this->recordBridgeTelemetryEvent(
                    $bridgeSessionUuid,
                    $source,
                    'backend.rfid_scan.student_not_found',
                    'El estudiante activo en cache ya no existe.',
                    [
                        'uid_normalized' => $input,
                        'student_id' => $estudianteId,
                        'camera_id' => $camara->id,
                        'camera_model' => $camara->modelo,
                        'context_key' => $contextKey,
                    ],
                    'warning',
                    $bridgeMetadata
                );

                return [
                    'body' => [
                        'ok' => false,
                        'status' => 'student_not_found',
                        'message' => 'Estudiante en contexto no encontrado. Vuelve a escanear carnet.',
                    ],
                    'status_code' => 409,
                ];
            }

            if ($camara->estado === 'Disponible') {
                $camara->update(['estado' => 'Prestada']);

                LogPrestamo::create([
                    'estudiante_id' => $estudianteActual->id,
                    'camara_id' => $camara->id,
                    'accion' => 'Prestamo',
                ]);

                Cache::forget($contextKey);
                $this->recordBridgeTelemetryEvent(
                    $bridgeSessionUuid,
                    $source,
                    'backend.rfid_scan.loan_ok',
                    "Prestamo exitoso para {$camara->modelo} desde el lector NFC/RFID.",
                    [
                        'uid_normalized' => $input,
                        'student_id' => $estudianteActual->id,
                        'student_name' => $estudianteActual->nombre,
                        'camera_id' => $camara->id,
                        'camera_model' => $camara->modelo,
                        'camera_state' => $camara->fresh()->estado,
                        'context_key' => $contextKey,
                    ],
                    'info',
                    $bridgeMetadata
                );

                return [
                    'body' => [
                        'ok' => true,
                        'status' => 'loan_ok',
                        'message' => "Prestamo exitoso: {$camara->modelo}",
                    ],
                    'status_code' => 200,
                ];
            }

            if ($camara->estado === 'Prestada') {
                $camara->update(['estado' => 'Disponible']);

                LogPrestamo::create([
                    'estudiante_id' => $estudianteActual->id,
                    'camara_id' => $camara->id,
                    'accion' => 'Devolucion',
                ]);

                Cache::forget($contextKey);
                $this->recordBridgeTelemetryEvent(
                    $bridgeSessionUuid,
                    $source,
                    'backend.rfid_scan.return_ok',
                    "Devolucion exitosa para {$camara->modelo} desde el lector NFC/RFID.",
                    [
                        'uid_normalized' => $input,
                        'student_id' => $estudianteActual->id,
                        'student_name' => $estudianteActual->nombre,
                        'camera_id' => $camara->id,
                        'camera_model' => $camara->modelo,
                        'camera_state' => $camara->fresh()->estado,
                        'context_key' => $contextKey,
                    ],
                    'info',
                    $bridgeMetadata
                );

                return [
                    'body' => [
                        'ok' => true,
                        'status' => 'return_ok',
                        'message' => "Devolucion exitosa: {$camara->modelo}",
                    ],
                    'status_code' => 200,
                ];
            }

            $this->recordBridgeTelemetryEvent(
                $bridgeSessionUuid,
                $source,
                'backend.rfid_scan.camera_unavailable',
                "La camara {$camara->modelo} esta en mantenimiento para flujo RFID.",
                [
                    'uid_normalized' => $input,
                    'camera_id' => $camara->id,
                    'camera_model' => $camara->modelo,
                    'camera_state' => $camara->estado,
                    'context_key' => $contextKey,
                ],
                'warning',
                $bridgeMetadata
            );

            return [
                'body' => [
                    'ok' => false,
                    'status' => 'camera_unavailable',
                    'message' => 'La camara esta en mantenimiento.',
                ],
                'status_code' => 409,
            ];
        }

        $registerPath = route('inventory.register', ['nfc_id' => $input], false);
        $this->recordBridgeTelemetryEvent(
            $bridgeSessionUuid,
            $source,
            'backend.rfid_scan.unregistered',
            'El lector RFID detecto un tag no registrado.',
            [
                'uid_normalized' => $input,
                'register_path' => $registerPath,
            ],
            'warning',
            $bridgeMetadata
        );

        return [
            'body' => [
                'ok' => false,
                'status' => 'unregistered',
                'message' => 'Tag no registrado.',
                'register_url' => route('inventory.register', ['nfc_id' => $input]),
                'register_path' => $registerPath,
                'nfc_id' => $input,
            ],
            'status_code' => 404,
        ];
    }

    private function normalizeNfcId(?string $value): string
    {
        $value = strtoupper(trim((string) $value));

        return preg_replace('/[\s\-:]+/', '', $value) ?? '';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function validateSignedReaderRequest(string $signingKey, string $uid, string $source, array $context): ?string
    {
        $nonce = trim((string) ($context['nonce'] ?? ''));
        $signature = strtolower(trim((string) ($context['signature'] ?? '')));

        if ($uid === '') {
            return 'empty_uid';
        }

        if ($nonce === '' || strlen($nonce) > 80) {
            return 'invalid_nonce';
        }

        if (preg_match('/^[a-f0-9]{40}$/', $signature) !== 1) {
            return 'invalid_signature_format';
        }

        $expectedSignature = sha1($signingKey . '|' . $uid . '|' . $source . '|' . $nonce);
        if (!hash_equals($expectedSignature, $signature)) {
            return 'signature_mismatch';
        }

        $nonceCacheKey = 'rfid:signature_nonce:' . sha1($source . '|' . $nonce);
        if (!Cache::add($nonceCacheKey, true, now()->addDay())) {
            return 'nonce_replay';
        }

        return null;
    }

    private function findEstudianteByNfc(string $id): ?Estudiante
    {
        return Estudiante::whereRaw(
            "REPLACE(REPLACE(REPLACE(UPPER(nfc_id), ' ', ''), '-', ''), ':', '') = ?",
            [$id]
        )->first();
    }

    private function findCamaraByNfc(string $id): ?Camara
    {
        return Camara::whereRaw(
            "REPLACE(REPLACE(REPLACE(UPPER(nfc_id), ' ', ''), '-', ''), ':', '') = ?",
            [$id]
        )->first();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function ensureBridgeSession(string $bridgeSessionUuid, string $source, array $metadata = []): void
    {
        if (trim($bridgeSessionUuid) === '') {
            return;
        }

        $this->telemetry->touchSession([
            'session_uuid' => $bridgeSessionUuid,
            'session_type' => 'bridge',
            'source' => $source,
            'status' => 'active',
            'timeout_seconds' => 60,
            'metadata' => $metadata,
            'last_seen_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    private function recordBridgeTelemetryEvent(
        string $bridgeSessionUuid,
        string $source,
        string $eventType,
        string $message,
        array $payload = [],
        string $level = 'info',
        array $metadata = [],
    ): void {
        $data = [
            'session_type' => 'bridge',
            'channel' => 'backend',
            'event_type' => $eventType,
            'level' => $level,
            'source' => $source,
            'message' => $message,
            'payload' => $payload,
            'metadata' => $metadata,
        ];

        if (trim($bridgeSessionUuid) !== '') {
            $data['session_uuid'] = $bridgeSessionUuid;
        }

        $this->telemetry->recordEvent($data);
    }
}
