@extends('layouts.app')

@section('title', 'Acceso Corporativo — CEDIS CDMX')
@section('body-class', 'bg-white text-zinc-950')

@section('content')
<div class="min-h-[calc(100vh-2.25rem)] grid grid-cols-1 lg:grid-cols-12">

    <!-- Panel Izquierdo (Negro #09090B): Ficha Operativa + Acceso Rápido por Puesto -->
    <section class="lg:col-span-6 bg-zinc-950 text-white industrial-grid p-6 sm:p-10 lg:p-12 flex flex-col justify-between border-b lg:border-b-0 lg:border-r border-zinc-800">
        <div>
            <div class="flex items-center space-x-3 mb-8">
                <div class="w-10 h-10 rounded-md bg-orange-500 flex items-center justify-center text-zinc-950 text-lg font-black">
                    <i class="fa-solid fa-warehouse"></i>
                </div>
                <div>
                    <div class="text-xs font-mono uppercase tracking-widest text-orange-500">HACKITVER 2026 · ÁREA 01</div>
                    <h1 class="text-xl font-extrabold tracking-tight text-white uppercase">CEDIS CDMX</h1>
                </div>
            </div>

            <div class="border-l-2 border-orange-500 pl-4 mb-8">
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white leading-tight">
                    De la Orden de Compra al Embarque sin fricción.
                </h2>
                <p class="text-sm text-zinc-400 mt-2 leading-relaxed">
                    Plataforma unificada de agendamiento de andenes, trazabilidad unitaria por QR, control volumétrico de racks y despacho normativo al 90%.
                </p>
            </div>

            <!-- Parámetros Físicos de Planta -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mb-8 font-mono tabular-nums">
                <div class="bg-zinc-900/90 border border-zinc-800 rounded-md p-3">
                    <div class="text-[10px] text-zinc-400 uppercase"> Flujo Diario</div>
                    <div class="text-lg font-bold text-white mt-0.5">35 / 20</div>
                    <div class="text-[10px] text-zinc-500">Entradas / Salidas</div>
                </div>
                <div class="bg-zinc-900/90 border border-zinc-800 rounded-md p-3">
                    <div class="text-[10px] text-zinc-400 uppercase">Almacén</div>
                    <div class="text-lg font-bold text-white mt-0.5">32 Racks</div>
                    <div class="text-[10px] text-zinc-500">1,024 Tarimas</div>
                </div>
                <div class="bg-zinc-900/90 border border-zinc-800 rounded-md p-3">
                    <div class="text-[10px] text-zinc-400 uppercase">Andenes</div>
                    <div class="text-lg font-bold text-white mt-0.5">4R · 3E</div>
                    <div class="text-[10px] text-zinc-500">08:00 a 18:00 h</div>
                </div>
                <div class="bg-zinc-900/90 border border-zinc-800 rounded-md p-3">
                    <div class="text-[10px] text-orange-500 uppercase">Regla Tortón</div>
                    <div class="text-lg font-bold text-orange-500 mt-0.5">&ge; 90%</div>
                    <div class="text-[10px] text-zinc-500">Peso y Volumen</div>
                </div>
            </div>

            <!-- Selector Directo de Demostración (5 Puestos) -->
            <div>
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-mono uppercase tracking-wider text-zinc-300">Acceso Directo por Puesto</span>
                    <span class="text-[10px] font-mono uppercase px-2 py-0.5 rounded bg-orange-500/15 text-orange-400 border border-orange-500/30">
                        Modo Evaluación · 1 Clic
                    </span>
                </div>

                <div class="divide-y divide-zinc-800 border border-zinc-800 rounded-lg bg-zinc-900/80 overflow-hidden">
                    @php
                        $roleMeta = [
                            'gerente' => ['num' => '01', 'tag' => 'DIRECCIÓN + COMPRAS + ROI'],
                            'jefe_embarque' => ['num' => '02', 'tag' => 'ANDENES + RACKS + TORTÓN ≥90%'],
                            'operador' => ['num' => '03', 'tag' => 'HANDHELD MÓVIL + CÁMARA QR'],
                            'proveedor' => ['num' => '04', 'tag' => 'FACTURA ÚNICA + CITAS + PDF'],
                            'sucursal' => ['num' => '05', 'tag' => 'VALIDACIÓN QR EN DESTINO'],
                        ];
                    @endphp
                    @foreach($demoUsers as $demo)
                        @php
                            $meta = $roleMeta[$demo->role] ?? ['num' => '00', 'tag' => strtoupper($demo->role)];
                        @endphp
                        <a href="{{ route('login.quick', $demo->role) }}"
                           class="flex items-center justify-between px-4 py-3 hover:bg-zinc-800/80 border-l-2 border-transparent hover:border-orange-500 transition group">
                            <div class="flex items-center space-x-3.5 min-w-0">
                                <span class="font-mono text-xs font-bold text-orange-500 tabular-nums">{{ $meta['num'] }}</span>
                                <div class="truncate">
                                    <div class="text-xs font-bold text-white group-hover:text-orange-400 transition truncate">{{ $demo->job_title }}</div>
                                    <div class="text-[11px] text-zinc-400 truncate">{{ $demo->name }} · <span class="font-mono">{{ $demo->email }}</span></div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2.5 shrink-0 ml-2">
                                <span class="hidden sm:inline-block text-[10px] font-mono tracking-wider px-2 py-0.5 rounded bg-zinc-950 text-zinc-300 border border-zinc-800">
                                    {{ $meta['tag'] }}
                                </span>
                                <i class="fa-solid fa-arrow-right text-xs text-zinc-500 group-hover:text-orange-500 group-hover:translate-x-0.5 transition"></i>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-8 pt-4 border-t border-zinc-900 flex items-center justify-between text-[11px] text-zinc-500 font-mono">
            <span>TRAZABILIDAD AUDITADA POR USUARIO</span>
            <span>ITVER · SISTEMAS E INDUSTRIAL</span>
        </div>
    </section>

    <!-- Panel Derecho (Blanco #FFFFFF): Formulario Corporativo -->
    <section class="lg:col-span-6 bg-white p-6 sm:p-10 lg:p-16 flex items-center justify-center" x-data="{ submitting: false }">
        <div class="w-full max-w-md">
            <div class="mb-8">
                <span class="inline-block text-xs font-mono font-semibold uppercase tracking-widest text-orange-600 mb-1">
                    Autenticación de Personal
                </span>
                <h2 class="text-2xl font-extrabold text-zinc-950 tracking-tight">Iniciar sesión operativa</h2>
                <p class="text-sm text-zinc-500 mt-1">
                    Ingrese sus credenciales asignadas. El sistema redirigirá automáticamente a su consola de trabajo.
                </p>
            </div>

            @if($errors->any())
                <div class="mb-6 p-3.5 rounded-md bg-zinc-950 border-l-4 border-orange-500 text-white text-xs flex items-start space-x-2.5">
                    <i class="fa-solid fa-triangle-exclamation text-orange-500 mt-0.5"></i>
                    <div>
                        <div class="font-bold uppercase tracking-wider text-[10px] text-orange-400">Error de acceso</div>
                        <p class="mt-0.5 text-zinc-200">{{ $errors->first() }}</p>
                    </div>
                </div>
            @endif

            <form action="{{ route('login.post') }}" method="POST" @submit="submitting = true" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-xs font-bold uppercase tracking-wider text-zinc-700 mb-1.5">
                        Correo Corporativo
                    </label>
                    <input id="email"
                           type="email"
                           name="email"
                           value="{{ old('email', 'gerente@cedis.com') }}"
                           required
                           autocomplete="email"
                           class="w-full bg-white border border-zinc-300 rounded-md px-3.5 py-2.5 text-sm text-zinc-950 font-medium focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-xs font-bold uppercase tracking-wider text-zinc-700">
                            Contraseña
                        </label>
                        <span class="text-[11px] font-mono text-zinc-500">Clave demo: <strong class="text-zinc-900">cedis2026</strong></span>
                    </div>
                    <input id="password"
                           type="password"
                           name="password"
                           value="cedis2026"
                           required
                           autocomplete="current-password"
                           class="w-full bg-white border border-zinc-300 rounded-md px-3.5 py-2.5 text-sm text-zinc-950 font-mono focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition">
                </div>

                <div class="flex items-center justify-between pt-1">
                    <label class="inline-flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" name="remember" checked
                               class="rounded border-zinc-300 text-orange-600 focus:ring-orange-500">
                        <span class="text-xs font-medium text-zinc-600">Mantener turno activo en terminal</span>
                    </label>
                </div>

                <button type="submit"
                        :disabled="submitting"
                        class="w-full bg-zinc-950 hover:bg-orange-600 disabled:bg-zinc-400 text-white font-bold text-xs uppercase tracking-wider py-3.5 px-4 rounded-md transition flex items-center justify-center space-x-2 cursor-pointer">
                    <span x-show="!submitting">Ingresar a Consola Asignada</span>
                    <span x-show="submitting" x-cloak>Verificando credenciales...</span>
                    <i class="fa-solid fa-arrow-right text-xs" x-show="!submitting"></i>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-zinc-200 grid grid-cols-3 gap-2 text-center font-mono">
                <div class="p-2 rounded bg-zinc-50 border border-zinc-200">
                    <div class="text-[10px] text-zinc-500">SEGURIDAD</div>
                    <div class="text-xs font-bold text-zinc-900">5 Roles RBAC</div>
                </div>
                <div class="p-2 rounded bg-zinc-50 border border-zinc-200">
                    <div class="text-[10px] text-zinc-500">VALIDACIÓN</div>
                    <div class="text-xs font-bold text-zinc-900">QR 2D Cámara</div>
                </div>
                <div class="p-2 rounded bg-zinc-50 border border-zinc-200">
                    <div class="text-[10px] text-zinc-500">DOCUMENTOS</div>
                    <div class="text-xs font-bold text-zinc-900">PDF + Láser</div>
                </div>
            </div>
        </div>
    </section>

</div>
@endsection
