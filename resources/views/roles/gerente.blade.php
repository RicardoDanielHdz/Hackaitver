@extends('layouts.app')

@section('title', 'Dirección General de Operaciones — CEDIS CDMX')
@section('module-code', '01 · DIRECCIÓN GENERAL · SUPERVISIÓN LOGÍSTICA Y AUDITORÍA')

@section('content')
@php
    $totalPallets = array_sum(array_column($branches, 'pallets'));
    $readyCount = count(array_filter($branches, static fn (array $b): bool => $b['ready_for_torton']));
@endphp

<div x-data="{
        tab: 'resumen',
        selectedBranchIndex: 0,
        get activeBranch() { return this.branches[this.selectedBranchIndex]; },
        branches: @js($branches)
     }"
     class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

    <!-- Alerta Urgente en Vivo de Salidas de Emergencia (<90%) -->
    @include('roles.partials.gerente-emergencias')

    <!-- 1. Barra Superior Administrativa de Dirección Logística -->
    <div class="bg-white border border-zinc-200 rounded-lg p-5 shadow-2xs">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-4 border-b border-zinc-200">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-2 py-0.5 text-[10px] font-mono font-bold uppercase tracking-widest bg-zinc-950 text-white rounded">
                        ERP DIRECCIÓN GENERAL · CEDIS CDMX
                    </span>
                    <span class="px-2 py-0.5 text-[10px] font-mono font-semibold bg-zinc-100 border border-zinc-300 text-zinc-700 rounded">
                        CONSOLIDACIÓN Y CROSS-DOCKING · 08:00 – 18:00 H
                    </span>
                    <span class="px-2 py-0.5 text-[10px] font-mono font-bold bg-emerald-50 border border-emerald-300 text-emerald-800 rounded">
                        ● OPERACIÓN NORMAL
                    </span>
                </div>
                <h1 class="text-xl sm:text-2xl font-extrabold text-zinc-950 tracking-tight mt-1">
                    Torre de Control Logístico y Consolidación de 32 Destinos
                </h1>
                <p class="text-xs text-zinc-500 mt-0.5">
                    Supervisión ejecutiva de andenes (R1–R4 / E1–E3), inventario en tránsito (1,024 posiciones), avisos de entrega (ASN), incidencias y autorización de excepciones de despacho.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                <button type="button"
                        @click="tab = 'usuarios'"
                        class="px-3.5 py-2 rounded-md bg-zinc-950 hover:bg-zinc-800 text-white text-xs font-bold uppercase tracking-wider transition cursor-pointer flex items-center space-x-1.5">
                    <i class="fa-solid fa-user-plus text-orange-400"></i>
                    <span>+ Añadir Usuario</span>
                </button>
                <button type="button"
                        @click="tab = 'racks'"
                        class="px-3.5 py-2 rounded-md border border-zinc-300 bg-white hover:border-zinc-950 text-zinc-900 text-xs font-bold uppercase tracking-wider transition cursor-pointer flex items-center space-x-1.5">
                    <i class="fa-solid fa-table-cells text-zinc-500"></i>
                    <span>Inspector 32 Racks</span>
                </button>
                <a href="{{ route('dashboard.embarques') }}"
                   class="px-4 py-2 rounded-md bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold uppercase tracking-wider transition flex items-center space-x-1.5 shadow-2xs">
                    <i class="fa-solid fa-truck-fast"></i>
                    <span>Consola de Embarques →</span>
                </a>
            </div>
        </div>

        <!-- 4 KPIs Administrativos Compactos -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 pt-4 font-mono tabular-nums text-xs">
            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block">Avisos de Entrega (ASN)</span>
                    <strong class="text-base font-extrabold text-zinc-950">28 / 35 citas</strong>
                    <span class="block text-[10px] font-sans text-zinc-500">4 Andenes (R1–R4) sin traslape</span>
                </div>
                <i class="fa-solid fa-truck-ramp-box text-zinc-400 text-base"></i>
            </div>

            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block">Inventario en Tránsito</span>
                    <strong class="text-base font-extrabold text-zinc-950">{{ $totalPallets }} / 1,024 pos</strong>
                    <span class="block text-[10px] font-sans text-zinc-500">{{ round(($totalPallets / 1024) * 100, 1) }}% en 32 Racks (4×8)</span>
                </div>
                <i class="fa-solid fa-boxes-stacked text-zinc-400 text-base"></i>
            </div>

            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block">Destinos Listos Tortón</span>
                    <strong class="text-base font-extrabold text-emerald-700">{{ $readyCount }} destinos &ge;90%</strong>
                    <span class="block text-[10px] font-sans text-zinc-500">16 / 20 salidas hoy (Prom. 93.4%)</span>
                </div>
                <i class="fa-solid fa-truck-fast text-orange-500 text-base"></i>
            </div>

            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block">Incidencias Contenidas</span>
                    <strong class="text-base font-extrabold text-zinc-950">{{ count($incidents) }} bloqueos QR</strong>
                    <span class="block text-[10px] font-sans text-zinc-500">100% filtradas en andén</span>
                </div>
                <i class="fa-solid fa-shield-halved text-zinc-400 text-base"></i>
            </div>
        </div>

        <!-- Barra de Pestañas Sobria (Solo Supervisión Logística) -->
        <nav class="flex space-x-6 overflow-x-auto mt-4 pt-2 border-t border-zinc-200 -mb-5 text-xs font-bold uppercase tracking-wider">
            <button type="button"
                    @click="tab = 'resumen'"
                    :class="tab === 'resumen' ? 'border-orange-500 text-zinc-950' : 'border-transparent text-zinc-500 hover:text-zinc-900'"
                    class="py-3 border-b-2 whitespace-nowrap transition cursor-pointer flex items-center space-x-2">
                <span class="font-mono text-[11px] text-orange-500">01</span>
                <span>Consolidación 32 Destinos, Andenes y Avisos (ASN)</span>
            </button>

            <button type="button"
                    @click="tab = 'racks'"
                    :class="tab === 'racks' ? 'border-orange-500 text-zinc-950' : 'border-transparent text-zinc-500 hover:text-zinc-900'"
                    class="py-3 border-b-2 whitespace-nowrap transition cursor-pointer flex items-center space-x-2">
                <span class="font-mono text-[11px] text-orange-500">02</span>
                <span>Plano 2D · 32 Racks (1,024 Posiciones)</span>
            </button>

            <button type="button"
                    @click="tab = 'usuarios'"
                    :class="tab === 'usuarios' ? 'border-orange-500 text-zinc-950' : 'border-transparent text-zinc-500 hover:text-zinc-900'"
                    class="py-3 border-b-2 whitespace-nowrap transition cursor-pointer flex items-center space-x-2">
                <span class="font-mono text-[11px] text-orange-500">03</span>
                <span>Usuarios y Perfiles ({{ count($usersList) }})</span>
            </button>
        </nav>
    </div>

    <!-- Contenido de las 3 Sub-Vistas de Dirección -->
    <div>
        <div x-show="tab === 'resumen'">
            @include('roles.partials.gerente-resumen', ['totalPallets' => $totalPallets, 'readyCount' => $readyCount])
        </div>

        <div x-show="tab === 'racks'" x-cloak>
            @include('roles.partials.gerente-racks')
        </div>

        <div x-show="tab === 'usuarios'" x-cloak>
            @include('roles.partials.gerente-usuarios')
        </div>
    </div>
</div>
@endsection
