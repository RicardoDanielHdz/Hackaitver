@extends('layouts.app')

@section('title', 'Acceso Corporativo — Dokra')
@section('body-class', 'bg-white text-zinc-950')

@section('content')
<div class="min-h-[calc(100vh-2.25rem)] grid grid-cols-1 lg:grid-cols-12">

    <!-- Panel Izquierdo Institucional (Sin accesos rápidos sin contraseña) -->
    <section class="lg:col-span-6 bg-zinc-950 text-white industrial-grid p-6 sm:p-10 lg:p-12 flex flex-col justify-between border-b lg:border-b-0 lg:border-r border-zinc-800">
        <div>
            <div class="flex items-center space-x-3 mb-8">
                <div class="w-10 h-10 rounded-md bg-orange-500 flex items-center justify-center text-zinc-950 text-lg font-black">
                    <i class="fa-solid fa-warehouse"></i>
                </div>
                <div>
                    <div class="text-xs font-mono uppercase tracking-widest text-orange-500">ERP LOGÍSTICO · CONTROL DE ACCESO</div>
                    <h1 class="text-xl font-extrabold tracking-tight text-white uppercase">Dokra</h1>
                </div>
            </div>

            <div class="border-l-2 border-orange-500 pl-4 mb-8">
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white leading-tight">
                    Plataforma Centralizada de Almacén, Acomodo y Embarques.
                </h2>
                <p class="text-sm text-zinc-400 mt-2 leading-relaxed">
                    Control de acceso estricto por perfil corporativo (RBAC). Cada cuenta accede exclusivamente a su módulo operativo asignado.
                </p>
            </div>

            <!-- Parámetros Físicos de Planta -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mb-8 font-mono tabular-nums">
                <div class="bg-zinc-900/90 border border-zinc-800 rounded-md p-3">
                    <div class="text-[10px] text-zinc-400 uppercase">Catálogo</div>
                    <div class="text-lg font-bold text-white mt-0.5">72 SKUs</div>
                    <div class="text-[10px] text-zinc-500">Papelería Auditada</div>
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

            <!-- Arquitectura Informativa de Roles RBAC (No clicable) -->
            <div class="bg-zinc-900/80 border border-zinc-800 rounded-lg p-4 space-y-2.5">
                <div class="flex items-center justify-between border-b border-zinc-800 pb-2">
                    <span class="text-xs font-mono uppercase tracking-wider text-zinc-300">Perfiles Operativos del Sistema</span>
                    <span class="text-[10px] font-mono uppercase px-2 py-0.5 rounded bg-zinc-950 text-orange-400 border border-zinc-800">
                        Autenticación Obligatoria
                    </span>
                </div>
                <div class="space-y-2 text-xs text-zinc-400 font-mono">
                    <div class="flex justify-between"><span>01 · Dirección General de Operaciones</span><span class="text-zinc-500">/gerente</span></div>
                    <div class="flex justify-between"><span>02 · Jefe de Embarque y Tráfico</span><span class="text-zinc-500">/embarques</span></div>
                    <div class="flex justify-between"><span>03 · Operador de Almacén y Acomodo</span><span class="text-zinc-500">/recepcion · /acomodo</span></div>
                    <div class="flex justify-between"><span>04 · Portal de Proveedor Externo</span><span class="text-zinc-500">/proveedor</span></div>
                    <div class="flex justify-between"><span>05 · Receptor en Sucursal Destino</span><span class="text-zinc-500">/sucursal</span></div>
                </div>
            </div>
        </div>

        <div class="mt-8 pt-4 border-t border-zinc-900 flex items-center justify-between text-[11px] text-zinc-500 font-mono">
            <span>SESIÓN INDIVIDUAL AUDITADA</span>
            <span>Dokra · ZONA METROPOLITANA</span>
        </div>
    </section>

    <!-- Panel Derecho: Formulario Único de Inicio de Sesión -->
    <section class="lg:col-span-6 bg-white p-6 sm:p-10 lg:p-16 flex items-center justify-center" x-data="{ submitting: false }">
        <div class="w-full max-w-md">
            <div class="mb-8">
                <span class="inline-block text-xs font-mono font-semibold uppercase tracking-widest text-orange-600 mb-1">
                    Control de Acceso Corporativo
                </span>
                <h2 class="text-2xl font-extrabold text-zinc-950 tracking-tight">Iniciar sesión</h2>
                <p class="text-sm text-zinc-500 mt-1">
                    Ingrese el correo electrónico y la contraseña correspondientes a su perfil. Para cambiar de usuario deberá cerrar la sesión activa.
                </p>
            </div>

            @if($errors->any())
                <div class="mb-6 p-3.5 rounded-md bg-zinc-950 border-l-4 border-orange-500 text-white text-xs flex items-start space-x-2.5">
                    <i class="fa-solid fa-triangle-exclamation text-orange-500 mt-0.5"></i>
                    <div>
                        <div class="font-bold uppercase tracking-wider text-[10px] text-orange-400">Acceso denegado</div>
                        <p class="mt-0.5 text-zinc-200">{{ $errors->first() }}</p>
                    </div>
                </div>
            @endif

            <form action="{{ route('login.post') }}" method="POST" @submit="submitting = true" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-xs font-bold uppercase tracking-wider text-zinc-700 mb-1.5">
                        Correo Electrónico
                    </label>
                    <input id="email"
                           type="email"
                           name="email"
                           value="{{ old('email') }}"
                           placeholder="usuario@cedis.com"
                           required
                           autofocus
                           autocomplete="username"
                           class="w-full bg-white border border-zinc-300 rounded-md px-3.5 py-2.5 text-sm text-zinc-950 font-medium focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition">
                </div>

                <div>
                    <label for="password" class="block text-xs font-bold uppercase tracking-wider text-zinc-700 mb-1.5">
                        Contraseña
                    </label>
                    <input id="password"
                           type="password"
                           name="password"
                           placeholder="••••••••••••"
                           required
                           autocomplete="current-password"
                           class="w-full bg-white border border-zinc-300 rounded-md px-3.5 py-2.5 text-sm text-zinc-950 font-mono focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition">
                </div>

                <div class="flex items-center justify-between pt-1">
                    <label class="inline-flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" name="remember"
                               class="rounded border-zinc-300 text-orange-600 focus:ring-orange-500">
                        <span class="text-xs font-medium text-zinc-600">Recordar sesión en este equipo</span>
                    </label>
                </div>

                <button type="submit"
                        :disabled="submitting"
                        class="w-full bg-zinc-950 hover:bg-orange-600 disabled:bg-zinc-400 text-white font-bold text-xs uppercase tracking-wider py-3.5 px-4 rounded-md transition flex items-center justify-center space-x-2 cursor-pointer">
                    <span x-show="!submitting">Iniciar Sesión</span>
                    <span x-show="submitting" x-cloak>Autenticando...</span>
                    <i class="fa-solid fa-arrow-right text-xs" x-show="!submitting"></i>
                </button>
            </form>
        </div>
    </section>

</div>
@endsection
