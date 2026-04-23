<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guia de uso - Control de Camaras</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @include('partials.unified-ui-head')
</head>

<body class="ui-body">
    <div class="ui-shell">
        <div class="ui-header-card mb-8">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p class="ui-kicker">Flujo de trabajo</p>
                    <h1 class="ui-title">Guia de uso</h1>
                    <p class="ui-subtitle">
                        Esta guia resume como usar el lector, en que orden escanear los tags y que ocurre cuando un tag todavia no esta registrado.
                    </p>
                </div>
                <div class="ui-actions">
                    <a href="{{ route('inventory.index') }}" class="ui-button ui-button--secondary">
                        Volver al inventario
                    </a>
                    <a href="{{ route('historial') }}" class="ui-button ui-button--accent">
                        Ver historial
                    </a>
                </div>
            </div>
        </div>

        <div class="mb-6 grid gap-4 lg:grid-cols-3">
            <div class="ui-stat-card ui-stat-card--primary">
                <p class="ui-kicker">1. Identificar</p>
                <p class="text-lg font-extrabold text-slate-800">Escanea el carnet</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    El estudiante queda activo para la siguiente lectura. Si no hay estudiante activo, una camara no puede prestarse ni devolverse.
                </p>
            </div>
            <div class="ui-stat-card">
                <p class="ui-kicker">2. Operar</p>
                <p class="text-lg font-extrabold text-slate-800">Escanea la camara</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Una camara disponible pasa a prestada. Una camara prestada pasa a disponible. Al terminar, el estudiante activo se borra.
                </p>
            </div>
            <div class="ui-stat-card ui-stat-card--accent">
                <p class="ui-kicker">3. Revisar</p>
                <p class="text-lg font-extrabold text-slate-800">Consulta el resultado</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    El inventario muestra el nuevo estado y el historial guarda quien retiro o devolvio cada camara.
                </p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            <section class="ui-card">
                <p class="ui-kicker">Uso diario</p>
                <h2 class="ui-section-title">Orden recomendado de escaneo</h2>

                <ol class="mt-5 grid gap-4">
                    <li class="rounded-2xl border border-[var(--ui-line)] bg-white/70 p-4">
                        <p class="font-bold text-slate-800">1. Abre el inventario en el equipo de trabajo.</p>
                        <p class="mt-1 text-sm leading-6 text-slate-600">
                            Tambien puedes abrir la URL compartida desde otro dispositivo conectado a la misma red.
                        </p>
                    </li>
                    <li class="rounded-2xl border border-[var(--ui-line)] bg-white/70 p-4">
                        <p class="font-bold text-slate-800">2. Escanea primero el tag del estudiante.</p>
                        <p class="mt-1 text-sm leading-6 text-slate-600">
                            El sistema debe mostrar el saludo del estudiante y pedir que se escanee una camara.
                        </p>
                    </li>
                    <li class="rounded-2xl border border-[var(--ui-line)] bg-white/70 p-4">
                        <p class="font-bold text-slate-800">3. Escanea el tag de la camara.</p>
                        <p class="mt-1 text-sm leading-6 text-slate-600">
                            Si esta disponible, queda prestada al estudiante. Si ya estaba prestada, se registra la devolucion.
                        </p>
                    </li>
                    <li class="rounded-2xl border border-[var(--ui-line)] bg-white/70 p-4">
                        <p class="font-bold text-slate-800">4. Para otra operacion, vuelve a empezar con el carnet.</p>
                        <p class="mt-1 text-sm leading-6 text-slate-600">
                            Despues de cada prestamo o devolucion, el sistema deja de tener estudiante activo para evitar operaciones accidentales.
                        </p>
                    </li>
                </ol>
            </section>

            <section class="ui-card">
                <p class="ui-kicker">Tags nuevos</p>
                <h2 class="ui-section-title">Como se enlaza un tag</h2>

                <div class="mt-5 space-y-4">
                    <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4">
                        <p class="font-bold text-blue-900">Si el tag es de un estudiante</p>
                        <p class="mt-1 text-sm leading-6 text-blue-800">
                            Al escanearlo por primera vez, el sistema abre el registro. Alli puedes asignarlo a un estudiante existente sin tag o crear un estudiante nuevo.
                        </p>
                    </div>
                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
                        <p class="font-bold text-emerald-900">Si el tag es de una camara</p>
                        <p class="mt-1 text-sm leading-6 text-emerald-800">
                            En la misma pantalla puedes asignarlo a una camara existente sin tag o crear una camara nueva con su estado inicial.
                        </p>
                    </div>
                    <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4">
                        <p class="font-bold text-amber-900">Regla importante</p>
                        <p class="mt-1 text-sm leading-6 text-amber-800">
                            Un tag debe pertenecer a una sola cosa: un estudiante o una camara. Si ya esta asignado, usa otro tag o revisa el registro existente.
                        </p>
                    </div>
                </div>
            </section>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <section class="ui-card">
                <p class="ui-kicker">Prestamos y devoluciones</p>
                <h2 class="ui-section-title">Que significa cada estado</h2>

                <div class="mt-5 grid gap-3">
                    <div class="flex flex-col gap-2 rounded-2xl border border-green-100 bg-green-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="font-bold text-green-900">Disponible</p>
                        <p class="text-sm leading-6 text-green-800">Al escanearla despues de un carnet, se registra un prestamo.</p>
                    </div>
                    <div class="flex flex-col gap-2 rounded-2xl border border-red-100 bg-red-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="font-bold text-red-900">Prestada</p>
                        <p class="text-sm leading-6 text-red-800">Al escanearla despues de un carnet, se registra una devolucion.</p>
                    </div>
                    <div class="flex flex-col gap-2 rounded-2xl border border-orange-100 bg-orange-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="font-bold text-orange-900">Mantenimiento</p>
                        <p class="text-sm leading-6 text-orange-800">No se presta ni se devuelve hasta cambiar su estado.</p>
                    </div>
                </div>
            </section>

            <section class="ui-card">
                <p class="ui-kicker">Casos comunes</p>
                <h2 class="ui-section-title">Que hacer si algo no fluye</h2>

                <div class="mt-5 space-y-4 text-sm leading-6 text-slate-600">
                    <p>
                        Si escaneas una camara antes del carnet, el sistema pedira escanear primero el carnet. Repite la operacion empezando por el estudiante.
                    </p>
                    <p>
                        Si aparece la pantalla de registro, el tag todavia no esta enlazado. Decide si corresponde a estudiante o camara antes de guardarlo.
                    </p>
                    <p>
                        Si el lector no reacciona, revisa el bloque de estado del lector en inventario. La telemetria operativa ayuda a confirmar si el bridge esta enviando lecturas.
                    </p>
                    <p>
                        Si necesitas auditar una entrega, abre el historial y busca el estudiante, la camara, la accion y la fecha registrada.
                    </p>
                </div>
            </section>
        </div>
    </div>
</body>

</html>
