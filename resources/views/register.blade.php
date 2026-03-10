<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Nuevo Tag - Control de Cámaras</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Check local time to apply dark mode preference if system uses it, 
        // though the main app seems light. We'll stick to the light theme of inventory.blade.php
    </script>
</head>

<body class="bg-gray-100 h-screen font-sans flex items-center justify-center">

    <div class="container mx-auto p-6 max-w-4xl">

        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Nuevo Tag Detectado</h1>
            <div
                class="inline-block bg-yellow-100 text-yellow-800 px-4 py-2 rounded-full font-mono text-lg font-bold border border-yellow-200">
                {{ $nfc_id }}
            </div>
            <p class="mt-4 text-gray-600">Este código no está registrado. ¿Qué deseas asignar?</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

            <!-- Student Registration Card -->
            <div
                class="bg-white p-8 rounded-xl shadow-lg border-t-4 border-blue-500 hover:shadow-xl transition duration-300">
                <div class="flex items-center gap-3 mb-6">
                    <div class="bg-blue-100 p-3 rounded-full text-blue-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800">Registrar Estudiante</h2>
                </div>

                <form action="{{ route('inventory.storeStudent') }}" method="POST">
                    @csrf
                    <input type="hidden" name="nfc_id" value="{{ $nfc_id }}">

                    <div class="mb-4">
                        <label for="estudiante_id" class="block text-gray-700 text-sm font-bold mb-2">Seleccionar Estudiante</label>
                        <select name="estudiante_id" id="estudiante_id"
                            class="shadow appearance-none border rounded w-full py-3 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                            required autofocus>
                            <option value="">-- Seleccionar --</option>
                            @foreach($estudiantes as $estudiante)
                                <option value="{{ $estudiante->id }}">{{ $estudiante->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="alias_student" class="block text-gray-700 text-sm font-bold mb-2">Alias (Opcional)</label>
                        <input type="text" name="alias" id="alias_student"
                            class="shadow appearance-none border rounded w-full py-3 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                            placeholder="Ej. Jefe de Grupo">
                    </div>

                    <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded focus:outline-none focus:shadow-outline transition duration-150">
                        Guardar Estudiante
                    </button>
                </form>
            </div>

            <!-- Camera Registration Card -->
            <div
                class="bg-white p-8 rounded-xl shadow-lg border-t-4 border-green-500 hover:shadow-xl transition duration-300">
                <div class="flex items-center gap-3 mb-6">
                    <div class="bg-green-100 p-3 rounded-full text-green-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800">Registrar Cámara</h2>
                </div>

                <form action="{{ route('inventory.storeCamera') }}" method="POST">
                    @csrf
                    <input type="hidden" name="nfc_id" value="{{ $nfc_id }}">

                    <div class="mb-4">
                        <label for="camara_id" class="block text-gray-700 text-sm font-bold mb-2">Seleccionar Cámara</label>
                        <select name="camara_id" id="camara_id"
                            class="shadow appearance-none border rounded w-full py-3 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-green-500 transition"
                            required>
                            <option value="">-- Seleccionar --</option>
                            @foreach($camaras as $camara)
                                <option value="{{ $camara->id }}">{{ $camara->modelo }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="alias_camera" class="block text-gray-700 text-sm font-bold mb-2">Alias (Opcional)</label>
                        <input type="text" name="alias" id="alias_camera"
                            class="shadow appearance-none border rounded w-full py-3 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-green-500 transition"
                            placeholder="Ej. Lente 50mm">
                    </div>

                    <button type="submit"
                        class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded focus:outline-none focus:shadow-outline transition duration-150">
                        Guardar Cámara
                    </button>
                </form>
            </div>

        </div>

        <div class="text-center mt-8">
            <a href="{{ route('inventory.index') }}"
                class="text-gray-500 hover:text-gray-700 text-sm underline">Cancelar y volver al inicio</a>
        </div>

    </div>

    <!-- Hidden NFC Scanner Form -->
    <form action="{{ route('inventory.scan') }}" method="POST" id="nfc_form">
        @csrf
        <input type="text" name="nfc_id" id="nfc_input" autocomplete="off"
            style="opacity: 0; position: absolute; top: -1000px; left: -1000px;">
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const nfcInput = document.getElementById('nfc_input');
            const nfcForm = document.getElementById('nfc_form');
            if (!nfcInput || !nfcForm) return;

            const isEditable = (el) => {
                if (!el) return false;
                const tag = (el.tagName || '').toUpperCase();
                return el.isContentEditable || tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
            };

            const submitCode = (raw) => {
                const code = (raw || '').trim();
                if (!code) return;
                nfcInput.value = code;
                nfcForm.submit();
            };

            const keepScannerFocus = () => {
                const active = document.activeElement;
                if (!isEditable(active) || active === nfcInput) {
                    nfcInput.focus({ preventScroll: true });
                }
            };

            if (window.NfcScanner) {
                new window.NfcScanner('nfc_input');
                nfcInput.addEventListener('nfc:scan', (e) => submitCode(e.detail?.id || ''));
            }

            nfcInput.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                submitCode(nfcInput.value);
            });

            let buffer = '';
            let lastKeyAt = 0;
            document.addEventListener('keydown', (e) => {
                if (e.defaultPrevented || e.ctrlKey || e.metaKey || e.altKey) return;

                const active = document.activeElement;
                if (isEditable(active) && active !== nfcInput) return;

                if (e.key === 'Enter') {
                    const value = buffer || nfcInput.value;
                    if (value) {
                        e.preventDefault();
                        submitCode(value);
                    }
                    buffer = '';
                    return;
                }

                if (e.key === 'Backspace') {
                    buffer = buffer.slice(0, -1);
                    nfcInput.value = buffer;
                    return;
                }

                if (e.key.length === 1) {
                    const now = Date.now();
                    if (now - lastKeyAt > 2000) buffer = '';
                    lastKeyAt = now;
                    buffer += e.key;
                    nfcInput.value = buffer;
                }
            });

            window.addEventListener('focus', keepScannerFocus);
            document.addEventListener('click', () => setTimeout(keepScannerFocus, 0));
            setInterval(keepScannerFocus, 1000);
            keepScannerFocus();
        });
    </script>
</body>

</html>
