<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario de Cámaras - Fotografía</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Hide the input but keep it focusable and functional */
        #nfc_input {
            opacity: 0.5;
            /* Visible for debugging, can be 0 for prod */
            position: absolute;
            top: -100px;
        }

        .status-dot {
            height: 15px;
            width: 15px;
            border-radius: 50%;
            display: inline-block;
        }
    </style>
</head>

<body class="bg-gray-100 h-screen font-sans">

    <div class="container mx-auto p-6">

        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div class="flex items-center gap-4">
                <h1 class="text-3xl font-bold text-gray-800">Control de Cámaras</h1>
                <a href="{{ route('historial') }}"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium py-2 px-4 rounded-lg shadow transition duration-150 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Ver Historial
                </a>
            </div>
            <div class="bg-white p-4 rounded-lg shadow-md min-w-[300px] text-center">
                @if(session('estudiante_actual'))
                    <p class="text-green-600 font-bold text-xl">
                        Hola, {{ session('estudiante_actual')->nombre }}
                    </p>
                    <p class="text-xs text-gray-500">Escanea una cámara para pedir/devolver</p>
                @else
                    <p class="text-gray-500 font-semibold">Esperando estudiante...</p>
                    <p class="text-xs text-gray-400">Escanea tu carnet para comenzar</p>
                @endif
            </div>
        </div>

        <!-- Feedback Messages -->
        @if(session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                <p>{{ session('success') }}</p>
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p>{{ session('error') }}</p>
            </div>
        @endif

        <!-- NFC Input Form -->
        <form action="{{ route('inventory.scan') }}" method="POST" id="nfc_form">
            @csrf
            <input type="text" name="nfc_id" id="nfc_input" autocomplete="off" autofocus>
        </form>

        <!-- Config Indicator -->
        <div class="text-center mb-4">
            <p class="text-sm text-gray-400">LECTOR NFC ACTIVO - MANTÉN LA VENTANA ACTIVA</p>
        </div>

        <!-- Cameras Grid/Table -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <table class="min-w-full leading-normal">
                <thead>
                    <tr>
                        <th
                            class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Modelo
                        </th>
                        <th
                            class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Estado
                        </th>
                        <th
                            class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            ID NFC
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($camaras as $camara)
                        <tr>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                <p class="text-gray-900 whitespace-no-wrap font-bold">
                                    {{ str_replace('Canon T7', 'Cámara', $camara->modelo) }}
                                </p>
                            </td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                @if($camara->estado === 'Disponible')
                                    <span class="relative inline-block px-3 py-1 font-semibold text-green-900 leading-tight">
                                        <span aria-hidden="true"
                                            class="absolute inset-0 bg-green-200 opacity-50 rounded-full"></span>
                                        <span class="relative">Disponible</span>
                                    </span>
                                @elseif($camara->estado === 'Prestada')
                                    <span class="relative inline-block px-3 py-1 font-semibold text-red-900 leading-tight">
                                        <span aria-hidden="true"
                                            class="absolute inset-0 bg-red-200 opacity-50 rounded-full"></span>
                                        <span class="relative">Prestada</span>
                                    </span>
                                    {{-- Optional: Show who has it if we queried logs. For now just show status. --}}
                                @else
                                    <span class="relative inline-block px-3 py-1 font-semibold text-orange-900 leading-tight">
                                        <span aria-hidden="true"
                                            class="absolute inset-0 bg-orange-200 opacity-50 rounded-full"></span>
                                        <span class="relative">Mantenimiento</span>
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                <p class="text-gray-500 whitespace-no-wrap font-mono">{{ $camara->nfc_id }}</p>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

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

            // If bundled scanner exists, keep compatibility with custom event flow.
            if (window.NfcScanner) {
                new window.NfcScanner('nfc_input');
                nfcInput.addEventListener('nfc:scan', (e) => submitCode(e.detail?.id || ''));
            }

            // Fallback: Enter inside hidden scanner input.
            nfcInput.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                submitCode(nfcInput.value);
            });

            // Global keyboard wedge capture (scanner-as-keyboard).
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
