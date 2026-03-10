<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Estudiante;
use App\Models\Camara;
use App\Models\LogPrestamo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class InventoryController extends Controller
{
    private function normalizeNfcId(?string $value): string
    {
        $value = strtoupper(trim((string) $value));
        // Preserve alphanumeric codes; only remove separators/spaces.
        return preg_replace('/[\s\-]+/', '', $value) ?? '';
    }

    private function findEstudianteByNfc(string $id): ?Estudiante
    {
        return Estudiante::whereRaw(
            "REPLACE(REPLACE(UPPER(nfc_id), ' ', ''), '-', '') = ?",
            [$id]
        )->first();
    }

    private function findCamaraByNfc(string $id): ?Camara
    {
        return Camara::whereRaw(
            "REPLACE(REPLACE(UPPER(nfc_id), ' ', ''), '-', '') = ?",
            [$id]
        )->first();
    }

    public function index()
    {
        $camaras = Camara::all();
        $estudianteActual = Session::get('estudiante_actual');

        return view('inventory', compact('camaras', 'estudianteActual'));
    }

    public function procesarInput(Request $request)
    {
        $input = $this->normalizeNfcId($request->input('nfc_id'));
        if ($input === '') {
            return redirect()->back()->with('error', 'Codigo vacio o invalido.');
        }

        // 1. Check if input is a Student
        $estudiante = $this->findEstudianteByNfc($input);

        if ($estudiante) {
            Session::put('estudiante_actual', $estudiante);
            return redirect()->back()->with('success', "Hola {$estudiante->nombre}, escanea una camara.");
        }

        // 2. Check if input is a Camera
        $camara = $this->findCamaraByNfc($input);

        if ($camara) {
            $estudianteActual = Session::get('estudiante_actual');

            if (!$estudianteActual) {
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
                return redirect()->back()->with('success', "Devolucion exitosa: {$camara->modelo}");
            }

            return redirect()->back()->with('error', 'La camara esta en mantenimiento.');
        }

        // 3. If neither, redirect to Registration
        return redirect()->route('inventory.register', ['nfc_id' => $input]);
    }

    public function procesarInputEsp(Request $request)
    {
        $configuredToken = (string) env('RFID_ESP_TOKEN', '');
        $providedToken = (string) $request->input('token', '');

        if ($configuredToken !== '' && !hash_equals($configuredToken, $providedToken)) {
            return response()->json([
                'ok' => false,
                'status' => 'unauthorized',
                'message' => 'Token invalido.',
            ], 401);
        }

        $raw = $request->input('uid', $request->input('nfc_id', $request->input('tag')));
        $input = $this->normalizeNfcId($raw);
        Log::info('rfid_esp_scan_received', [
            'source' => $request->input('source', 'esp8266'),
            'uid_raw' => $raw,
            'uid_normalized' => $input,
            'ip' => $request->ip(),
        ]);
        if ($input === '') {
            return response()->json([
                'ok' => false,
                'status' => 'invalid_input',
                'message' => 'UID vacio o invalido.',
            ], 422);
        }

        $source = trim((string) $request->input('source', 'esp8266'));
        if ($source === '') {
            $source = 'esp8266';
        }
        $contextKey = 'rfid:current_student:' . $source;

        // 1) Student tag
        $estudiante = $this->findEstudianteByNfc($input);
        if ($estudiante) {
            Cache::put($contextKey, $estudiante->id, now()->addMinutes(20));

            return response()->json([
                'ok' => true,
                'status' => 'student_ok',
                'message' => "Hola {$estudiante->nombre}, escanea una camara.",
                'student' => [
                    'id' => $estudiante->id,
                    'nombre' => $estudiante->nombre,
                ],
            ]);
        }

        // 2) Camera tag
        $camara = $this->findCamaraByNfc($input);
        if ($camara) {
            $estudianteId = Cache::get($contextKey);
            if (!$estudianteId) {
                return response()->json([
                    'ok' => false,
                    'status' => 'student_required',
                    'message' => 'Escanea primero el carnet del estudiante.',
                ], 409);
            }

            $estudianteActual = Estudiante::find($estudianteId);
            if (!$estudianteActual) {
                Cache::forget($contextKey);

                return response()->json([
                    'ok' => false,
                    'status' => 'student_not_found',
                    'message' => 'Estudiante en contexto no encontrado. Vuelve a escanear carnet.',
                ], 409);
            }

            if ($camara->estado === 'Disponible') {
                $camara->update(['estado' => 'Prestada']);

                LogPrestamo::create([
                    'estudiante_id' => $estudianteActual->id,
                    'camara_id' => $camara->id,
                    'accion' => 'Prestamo',
                ]);

                Cache::forget($contextKey);

                return response()->json([
                    'ok' => true,
                    'status' => 'loan_ok',
                    'message' => "Prestamo exitoso: {$camara->modelo}",
                ]);
            }

            if ($camara->estado === 'Prestada') {
                $camara->update(['estado' => 'Disponible']);

                LogPrestamo::create([
                    'estudiante_id' => $estudianteActual->id,
                    'camara_id' => $camara->id,
                    'accion' => 'Devolucion',
                ]);

                Cache::forget($contextKey);

                return response()->json([
                    'ok' => true,
                    'status' => 'return_ok',
                    'message' => "Devolucion exitosa: {$camara->modelo}",
                ]);
            }

            return response()->json([
                'ok' => false,
                'status' => 'camera_unavailable',
                'message' => 'La camara esta en mantenimiento.',
            ], 409);
        }

        // 3) Unregistered tag
        return response()->json([
            'ok' => false,
            'status' => 'unregistered',
            'message' => 'Tag no registrado.',
            'register_url' => route('inventory.register', ['nfc_id' => $input]),
            'nfc_id' => $input,
        ], 404);
    }

    public function pingEsp()
    {
        return response()->json([
            'ok' => true,
            'message' => 'ESP endpoint reachable',
            'time' => now()->toDateTimeString(),
        ]);
    }

    public function showRegister($nfc_id)
    {
        $estudiantes = Estudiante::whereNull('nfc_id')->get();
        $camaras = Camara::whereNull('nfc_id')->get();
        return view('register', compact('nfc_id', 'estudiantes', 'camaras'));
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

        $estudiante = Estudiante::find($request->estudiante_id);
        $estudiante->update([
            'nfc_id' => $request->nfc_id,
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

        $camara = Camara::find($request->camara_id);
        $camara->update([
            'nfc_id' => $request->nfc_id,
            'alias' => $request->alias,
        ]);

        return redirect()->route('inventory.index')->with('success', "Tag asignado a camara: {$camara->modelo}");
    }
}
