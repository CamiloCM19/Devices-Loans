<?php

namespace App\Http\Controllers;

use App\Models\Camara;
use Illuminate\Http\Request;
use App\Models\Estudiante;
use App\Models\HardwareTelemetrySession;
use App\Models\LogPrestamo;
use App\Services\RfidScanProcessor;
use App\Support\HardwareTelemetryRecorder;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function __construct(
        private readonly HardwareTelemetryRecorder $telemetry,
        private readonly RfidScanProcessor $rfidScanProcessor,
    ) {
    }

    private function normalizeNfcId(?string $value): string
    {
        $value = strtoupper(trim((string) $value));
        // Preserve alphanumeric codes; only remove separators/spaces.
        return preg_replace('/[\s\-:]+/', '', $value) ?? '';
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

    public function index()
    {
        $camaras = Camara::all();
        $estudianteActual = Session::get('estudiante_actual');
        $latestBridgeSession = HardwareTelemetrySession::query()
            ->where('session_type', 'bridge')
            ->orderByDesc('last_seen_at')
            ->first();

        return view('inventory', compact('camaras', 'estudianteActual', 'latestBridgeSession'));
    }

    public function procesarInput(Request $request)
    {
        $input = $this->normalizeNfcId($request->input('nfc_id'));
        $telemetryContext = [
            'telemetry_session_uuid' => $request->input('telemetry_session_uuid'),
            'source' => 'inventory-web',
            'flow' => 'web_scan',
            'input' => $input,
            'uid_raw' => $request->input('nfc_id'),
        ];

        $this->recordWebTelemetryEvent(
            $telemetryContext,
            'backend.web_scan.received',
            'El backend recibio un escaneo desde la pagina web.',
            [
                'uid_normalized' => $input,
            ]
        );

        if ($input === '') {
            $this->recordWebTelemetryEvent(
                $telemetryContext,
                'backend.web_scan.invalid_input',
                'La pagina envio un UID vacio o invalido.',
                [
                    'uid_normalized' => $input,
                ],
                'warning'
            );
            return redirect()->back()->with('error', 'Codigo vacio o invalido.');
        }

        // 1. Check if input is a Student
        $estudiante = $this->findEstudianteByNfc($input);

        if ($estudiante) {
            Session::put('estudiante_actual', $estudiante);
            $this->recordWebTelemetryEvent(
                $telemetryContext,
                'backend.web_scan.student_ok',
                "Se reconocio al estudiante {$estudiante->nombre}.",
                [
                    'uid_normalized' => $input,
                    'student_id' => $estudiante->id,
                    'student_name' => $estudiante->nombre,
                ]
            );
            return redirect()->back()->with('success', "Hola {$estudiante->nombre}, escanea una camara.");
        }

        // 2. Check if input is a Camera
        $camara = $this->findCamaraByNfc($input);

        if ($camara) {
            $estudianteActual = Session::get('estudiante_actual');

            if (!$estudianteActual) {
                $this->recordWebTelemetryEvent(
                    $telemetryContext,
                    'backend.web_scan.student_required',
                    'Se detecto una camara, pero no habia estudiante activo en sesion web.',
                    [
                        'uid_normalized' => $input,
                        'camera_id' => $camara->id,
                        'camera_model' => $camara->modelo,
                    ],
                    'warning'
                );
                return redirect()->back()->with('error', 'Escanea primero tu carnet.');
            }

            if ($camara->estado === 'Disponible') {
                $camara->update(['estado' => 'Prestada']);

                LogPrestamo::create([
                    'estudiante_id' => $estudianteActual->id,
                    'camara_id' => $camara->id,
                    'accion' => 'Prestamo',
                ]);

                Session::forget('estudiante_actual');
                $this->recordWebTelemetryEvent(
                    $telemetryContext,
                    'backend.web_scan.loan_ok',
                    "Prestamo exitoso para {$camara->modelo}.",
                    [
                        'uid_normalized' => $input,
                        'student_id' => $estudianteActual->id,
                        'student_name' => $estudianteActual->nombre,
                        'camera_id' => $camara->id,
                        'camera_model' => $camara->modelo,
                        'camera_state' => $camara->fresh()->estado,
                    ]
                );
                return redirect()->back()->with('success', "Prestamo exitoso: {$camara->modelo}");
            }

            if ($camara->estado === 'Prestada') {
                $camara->update(['estado' => 'Disponible']);

                LogPrestamo::create([
                    'estudiante_id' => $estudianteActual->id,
                    'camara_id' => $camara->id,
                    'accion' => 'Devolucion',
                ]);

                Session::forget('estudiante_actual');
                $this->recordWebTelemetryEvent(
                    $telemetryContext,
                    'backend.web_scan.return_ok',
                    "Devolucion exitosa para {$camara->modelo}.",
                    [
                        'uid_normalized' => $input,
                        'student_id' => $estudianteActual->id,
                        'student_name' => $estudianteActual->nombre,
                        'camera_id' => $camara->id,
                        'camera_model' => $camara->modelo,
                        'camera_state' => $camara->fresh()->estado,
                    ]
                );
                return redirect()->back()->with('success', "Devolucion exitosa: {$camara->modelo}");
            }

            $this->recordWebTelemetryEvent(
                $telemetryContext,
                'backend.web_scan.camera_unavailable',
                "La camara {$camara->modelo} esta en mantenimiento.",
                [
                    'uid_normalized' => $input,
                    'camera_id' => $camara->id,
                    'camera_model' => $camara->modelo,
                    'camera_state' => $camara->estado,
                ],
                'warning'
            );
            return redirect()->back()->with('error', 'La camara esta en mantenimiento.');
        }

        // 3. If neither, redirect to Registration
        $this->recordWebTelemetryEvent(
            $telemetryContext,
            'backend.web_scan.unregistered',
            'El tag escaneado desde la pagina no esta registrado.',
            [
                'uid_normalized' => $input,
                'register_path' => route('inventory.register', ['nfc_id' => $input], false),
            ],
            'warning'
        );
        return redirect()->route('inventory.register', ['nfc_id' => $input]);
    }

    public function procesarInputRfidApi(Request $request)
    {
        $result = $this->rfidScanProcessor->process(
            $request->input('uid', $request->input('nfc_id', $request->input('tag'))),
            [
                'token' => $request->input('token', ''),
                'source' => $request->input('source', 'nfc-reader'),
                'reader_model' => $request->input('reader_model', $request->input('reader', env('RFID_READER_DRIVER', 'auto'))),
                'bridge_session_uuid' => $request->input('bridge_session_uuid', ''),
                'bridge_transport' => $request->input('bridge_transport', 'serial'),
                'bridge_mode' => $request->input('bridge_mode', 'api'),
                'bridge_host' => $request->input('bridge_host'),
                'bridge_pid' => $request->input('bridge_pid'),
                'ip' => $request->ip(),
            ]
        );

        return response()->json($result['body'], $result['status_code']);
    }

    public function pingRfid()
    {
        return response()->json([
            'ok' => true,
            'message' => 'RFID endpoint reachable',
            'time' => now()->toDateTimeString(),
        ]);
    }

    public function showRegister($nfc_id)
    {
        $normalizedNfcId = $this->normalizeNfcId($nfc_id);
        $estudiantes = Estudiante::query()
            ->whereNull('nfc_id')
            ->orderBy('nombre')
            ->get();
        $camaras = Camara::query()
            ->whereNull('nfc_id')
            ->orderByRaw("CASE estado WHEN 'Disponible' THEN 0 WHEN 'Prestada' THEN 1 ELSE 2 END")
            ->orderBy('modelo')
            ->get();

        if ($normalizedNfcId === '') {
            return redirect()
                ->route('inventory.index')
                ->with('error', 'El tag detectado no es valido para registrar.');
        }

        return view('register', [
            'nfc_id' => $normalizedNfcId,
            'estudiantes' => $estudiantes,
            'camaras' => $camaras,
        ]);
    }

    public function storeStudent(Request $request)
    {
        $request->merge([
            'nfc_id' => $this->normalizeNfcId($request->input('nfc_id')),
        ]);

        $request->validate([
            'nfc_id' => 'required|unique:estudiantes,nfc_id',
            'estudiante_id' => 'required|exists:estudiantes,id',
            'alias' => 'nullable|string|max:255',
        ]);

        $nfcId = $request->input('nfc_id');
        $this->assertNfcIsAvailableForAssignment($nfcId, 'student');

        $estudiante = Estudiante::query()
            ->whereKey($request->estudiante_id)
            ->whereNull('nfc_id')
            ->first();

        if (!$estudiante) {
            throw ValidationException::withMessages([
                'estudiante_id' => 'Selecciona un estudiante pendiente de asignar.',
            ]);
        }

        $estudiante->update([
            'nfc_id' => $nfcId,
            'alias' => $request->alias,
        ]);

        // Auto-login after registration
        Session::put('estudiante_actual', $estudiante);

        return redirect()->route('inventory.index')->with('success', "Tag asignado a: {$estudiante->nombre}. Escanea una camara.");
    }

    public function storeCamera(Request $request)
    {
        $request->merge([
            'nfc_id' => $this->normalizeNfcId($request->input('nfc_id')),
        ]);

        $request->validate([
            'nfc_id' => 'required|unique:camaras,nfc_id',
            'camara_id' => 'required|exists:camaras,id',
            'alias' => 'nullable|string|max:255',
        ]);

        $nfcId = $request->input('nfc_id');
        $this->assertNfcIsAvailableForAssignment($nfcId, 'camera');

        $camara = Camara::query()
            ->whereKey($request->camara_id)
            ->whereNull('nfc_id')
            ->first();

        if (!$camara) {
            throw ValidationException::withMessages([
                'camara_id' => 'Selecciona una camara pendiente de asignar.',
            ]);
        }

        $camara->update([
            'nfc_id' => $nfcId,
            'alias' => $request->alias,
        ]);

        return redirect()->route('inventory.index')->with('success', "Tag asignado a camara: {$camara->modelo}");
    }

    public function storeNewStudent(Request $request)
    {
        $request->merge([
            'nfc_id' => $this->normalizeNfcId($request->input('nfc_id')),
        ]);

        $validated = $request->validate([
            'nfc_id' => 'required|unique:estudiantes,nfc_id',
            'nombre' => 'required|string|max:255',
            'alias' => 'nullable|string|max:255',
        ]);

        $this->assertNfcIsAvailableForAssignment($validated['nfc_id'], 'student');

        $estudiante = Estudiante::create([
            'nfc_id' => $validated['nfc_id'],
            'nombre' => $validated['nombre'],
            'alias' => $validated['alias'] ?? null,
            'activo' => true,
        ]);

        Session::put('estudiante_actual', $estudiante);

        return redirect()
            ->route('inventory.index')
            ->with('success', "Se creo y asigno el tag a: {$estudiante->nombre}. Escanea una camara.");
    }

    public function storeNewCamera(Request $request)
    {
        $request->merge([
            'nfc_id' => $this->normalizeNfcId($request->input('nfc_id')),
        ]);

        $validated = $request->validate([
            'nfc_id' => 'required|unique:camaras,nfc_id',
            'modelo' => 'required|string|max:255',
            'alias' => 'nullable|string|max:255',
            'estado' => 'required|in:Disponible,Prestada,Mantenimiento',
        ]);

        $this->assertNfcIsAvailableForAssignment($validated['nfc_id'], 'camera');

        $camara = Camara::create([
            'nfc_id' => $validated['nfc_id'],
            'modelo' => $validated['modelo'],
            'alias' => $validated['alias'] ?? null,
            'estado' => $validated['estado'],
        ]);

        return redirect()
            ->route('inventory.index')
            ->with('success', "Se creo y asigno el tag a la camara: {$camara->modelo}");
    }

    private function assertNfcIsAvailableForAssignment(string $nfcId, string $entityType): void
    {
        $studentAlreadyExists = Estudiante::query()->whereRaw(
            "REPLACE(REPLACE(REPLACE(UPPER(nfc_id), ' ', ''), '-', ''), ':', '') = ?",
            [$nfcId]
        )->exists();

        $cameraAlreadyExists = Camara::query()->whereRaw(
            "REPLACE(REPLACE(REPLACE(UPPER(nfc_id), ' ', ''), '-', ''), ':', '') = ?",
            [$nfcId]
        )->exists();

        if (
            ($entityType === 'student' && $cameraAlreadyExists)
            || ($entityType === 'camera' && $studentAlreadyExists)
            || ($entityType === 'student' && $studentAlreadyExists)
            || ($entityType === 'camera' && $cameraAlreadyExists)
        ) {
            throw ValidationException::withMessages([
                'nfc_id' => 'Este tag ya esta asignado. Usa otro tag o revisa el registro existente.',
            ]);
        }
    }

    private function recordWebTelemetryEvent(
        array $context,
        string $eventType,
        string $message,
        array $payload = [],
        string $level = 'info',
    ): void {
        $sessionUuid = trim((string) ($context['telemetry_session_uuid'] ?? ''));
        if ($sessionUuid === '') {
            return;
        }

        $this->telemetry->recordEvent([
            'session_uuid' => $sessionUuid,
            'session_type' => 'web',
            'channel' => 'backend',
            'event_type' => $eventType,
            'level' => $level,
            'source' => $context['source'] ?? 'inventory-web',
            'message' => $message,
            'payload' => array_merge([
                'flow' => $context['flow'] ?? 'web_scan',
                'uid_raw' => $context['uid_raw'] ?? null,
                'uid_normalized' => $context['input'] ?? null,
            ], $payload),
        ]);
    }

}
