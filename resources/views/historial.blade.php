<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Prestamos - Control de Camaras</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @include('partials.unified-ui-head')
</head>

<body class="ui-body">

    <div class="ui-shell">
        <div class="ui-header-card mb-8">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="ui-kicker">Trazabilidad</p>
                    <h1 class="ui-title">Historial de Movimientos</h1>
                    <p class="ui-subtitle">
                        Registro completo de prestamos y devoluciones con la misma interfaz clara del resto del sistema.
                    </p>
                </div>
                <div class="ui-actions">
                    <a href="{{ route('inventory.index') }}" class="ui-button ui-button--secondary">
                        Volver al inventario
                    </a>
                </div>
            </div>
        </div>

        <div class="ui-table-card">
            <div class="border-b border-[var(--ui-line)] px-6 py-5">
                <p class="ui-kicker">Historial</p>
                <h2 class="ui-section-title">Movimientos registrados</h2>
                <p class="ui-section-copy">Consulta quien retiro o devolvio cada camara y en que momento ocurrio.</p>
            </div>

            <div class="ui-table-wrap">
                <table class="ui-data-table">
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>Camara</th>
                            <th class="text-center">Accion</th>
                            <th>Fecha y hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td class="whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div
                                            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-600">
                                            {{ substr($log->estudiante->nombre ?? '?', 0, 1) }}
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ $log->estudiante->nombre ?? 'Desconocido' }}
                                            </div>
                                            <div class="text-xs text-gray-500">{{ $log->estudiante_id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ str_replace('Canon T7', 'Camara', $log->camara->modelo ?? 'Desconocido') }}
                                    </div>
                                    <div class="text-xs font-mono text-gray-500">{{ $log->camara_id }}</div>
                                </td>
                                <td class="whitespace-nowrap text-center">
                                    @if($log->accion === 'Prestamo')
                                        <span
                                            class="inline-flex items-center rounded-full border border-red-200 bg-red-100 px-3 py-1 text-xs font-medium text-red-800">
                                            <svg class="mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                                                </path>
                                            </svg>
                                            Prestamo
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center rounded-full border border-green-200 bg-green-100 px-3 py-1 text-xs font-medium text-green-800">
                                            <svg class="mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1">
                                                </path>
                                            </svg>
                                            Devolucion
                                        </span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap">
                                    <div class="text-sm font-semibold text-gray-900">{{ $log->created_at->format('d/m/Y') }}</div>
                                    <div class="text-xs text-gray-500">{{ $log->created_at->format('h:i A') }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-10 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="mb-3 h-12 w-12 text-gray-300" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01">
                                            </path>
                                        </svg>
                                        <p class="text-lg font-medium">No hay registros de movimientos aun.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>

</html>
