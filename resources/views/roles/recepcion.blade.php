@extends('layouts.app')

@section('title', 'ERP de Recepción QR y Etiquetado Láser Continuo — Dokra')
@section('module-code', '03 · OPERADOR Dokra · LLEGADAS PROGRAMADAS, ESCANEO QR Y ETIQUETA LÁSER CONTINUA')

@section('extra-head')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
@endsection

@section('content')
@php
    $formattedArrivals = $scheduledArrivals->map(static fn ($a) => [
        'id' => $a->id,
        'folio' => $a->folio,
        'supplier_name' => $a->supplier_name,
        'dock_code' => $a->dock_code,
        'eta_window' => $a->eta_window,
        'branch_id' => $a->branch_id,
        'branch_code' => $a->branch?->code ?? 'SUC-01',
        'branch_name' => $a->branch?->name ?? 'Centro Histórico',
        'rack_code' => $a->branch?->rack_code ?? 'RACK-01',
        'product_id' => $a->product_id,
        'sku' => $a->product?->sku ?? 'PAP-007',
        'product_name' => $a->product?->name ?? 'Producto Papelería',
        'stow_zone' => $a->product?->stow_zone ?? 'Base / Pesado',
        'master_factor' => (int) ($a->product?->master_factor ?? 10),
        'master_weight_kg' => (float) ($a->product?->master_weight_kg ?? 10.0),
        'boxes_expected' => (int) $a->boxes_expected,
        'boxes_received' => (int) $a->boxes_received,
        'status' => $a->status,
        'supplier_label_url' => route('wms.qr.label', ['arrival_id' => $a->id], false),
    ])->values();

    $formattedLaserQueue = $recentLaserItems->map(static fn ($item) => [
        'pallet_item_id' => $item->id,
        'internal_label_code' => $item->internal_label_code,
        'scanned_bulto' => $item->scanned_bulto_code ?: $item->batch_code,
        'sku' => $item->product?->sku ?? 'SKU',
        'product_name' => $item->product?->name ?? 'Producto',
        'boxes' => $item->quantity_boxes,
        'weight_kg' => $item->total_weight_kg,
        'pallet_code' => $item->pallet?->code ?? 'TARIMA',
        'stow_zone' => $item->product?->stow_zone ?? 'Estándar',
        'zone_priority' => $item->assigned_zone_priority,
        'layer_index' => $item->layer_index,
        'rack_coordinate' => $item->planned_rack_coordinate ?: (($item->pallet?->branch?->rack_code ?? 'RACK-01').' · EN PISO'),
        'origin_supplier' => $item->origin_supplier ?? 'Proveedor Autorizado',
        'origin_dock' => $item->origin_dock ?? 'R2',
        'origin_folio' => $item->batch_code,
        'outbound_branch' => ($item->pallet?->branch?->code ?? 'SUC-01').' · '.($item->pallet?->branch?->name ?? 'CDMX'),
        'outbound_dock_target' => $item->outbound_dock_target ?? 'ANDÉN E1 · OLA 1 TORTÓN',
        'outbound_cutoff' => $item->outbound_cutoff ?? 'SALIDA PRÓXIMO CORTE (≥90%)',
        'verified_at' => $item->created_at?->format('H:i:s') ?? now()->format('H:i:s'),
        'qr_data_uri' => null,
        'print_url' => route('wms.qr.laser-sheet', ['ids' => $item->id], false),
    ])->values();
@endphp

<div x-data="handheldScanner({
        csrfToken: @js(csrf_token()),
        scanUrl: @js(route('wms.qr.scan', [], false)),
        laserSheetBaseUrl: @js(route('wms.qr.laser-sheet', [], false)),
        initialArrivals: @js($formattedArrivals),
        initialLaserQueue: @js($formattedLaserQueue),
     })"
     class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

    <!-- ===================================================================== -->
    <!-- 1. BARRA SUPERIOR ADMINISTRATIVA DE RECEPCIÓN Y ROLLO LÁSER CONTINUO  -->
    <!-- ===================================================================== -->
    <div class="bg-white border border-zinc-200 rounded-lg p-5 shadow-2xs">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-4 border-b border-zinc-200">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-2 py-0.5 text-[10px] font-mono font-bold uppercase tracking-widest bg-zinc-950 text-white rounded">
                        ERP RECEPCIÓN · TERMINAL ZEBRA TC22
                    </span>
                    <span class="px-2 py-0.5 text-[10px] font-mono font-semibold bg-zinc-100 border border-zinc-300 text-zinc-700 rounded">
                        ANDENES ENTRADA R1–R4
                    </span>
                    <span class="px-2 py-0.5 text-[10px] font-mono font-bold rounded border"
                          :class="continuousAutoPrint ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : 'bg-zinc-100 border-zinc-300 text-zinc-700'"
                          x-text="continuousAutoPrint ? '● ROLLO LÁSER CONTINUO ACTIVO' : '● COLA LÁSER EN ACUMULACIÓN'"></span>
                </div>
                <h1 class="text-xl sm:text-2xl font-extrabold text-zinc-950 tracking-tight mt-1">
                    Registro de Llegadas, Comprobación QR y Etiquetado Láser Continuo
                </h1>
                <p class="text-xs text-zinc-500 mt-0.5">
                    Consulta qué llega, en qué andén y cuándo; escanea la etiqueta integrada del proveedor y emite de forma continua la segunda etiqueta láser (Acomodo, Origen y Salida).
                </p>
            </div>

            <!-- Acciones Principales del Operador -->
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <a :href="continuousSheetUrl"
                   target="_blank"
                   class="px-3.5 py-2 rounded-md bg-zinc-950 hover:bg-zinc-800 text-white text-xs font-bold uppercase tracking-wider flex items-center space-x-1.5">
                    <i class="fa-solid fa-print text-orange-400"></i>
                    <span>Imprimir Tira Continua Láser (<span x-text="laserQueue.length"></span>)</span>
                </a>
                <a href="{{ route('wms.index') }}"
                   class="px-3.5 py-2 rounded-md bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold uppercase tracking-wider transition flex items-center space-x-1.5">
                    <i class="fa-solid fa-cubes-stacked"></i>
                    <span>Motor de Acomodo y Racks →</span>
                </a>
            </div>
        </div>

        <!-- 4 KPIs Vivos del Operador -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 pt-4 font-mono tabular-nums text-xs">
            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block">Partidas por Arribar</span>
                    <strong class="text-base font-extrabold text-zinc-950" x-text="arrivals.length + ' programadas'"></strong>
                </div>
                <i class="fa-solid fa-calendar-check text-zinc-400 text-base"></i>
            </div>

            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block">Comprobación QR Proveedor</span>
                    <strong class="text-base font-extrabold text-zinc-950" x-text="totalReceivedBoxes + ' / ' + totalExpectedBoxes + ' cajas'"></strong>
                </div>
                <i class="fa-solid fa-qrcode text-orange-500 text-base"></i>
            </div>

            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block">Etiquetas Láser Emitidas</span>
                    <strong class="text-base font-extrabold text-emerald-700" x-text="laserQueue.length + ' en tira'"></strong>
                </div>
                <i class="fa-solid fa-tag text-zinc-400 text-base"></i>
            </div>

            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block">Impresión Continua Auto</span>
                    <button type="button"
                            @click="continuousAutoPrint = !continuousAutoPrint"
                            class="mt-0.5 px-2 py-0.5 rounded text-[11px] font-extrabold border cursor-pointer"
                            :class="continuousAutoPrint ? 'bg-zinc-950 text-white border-zinc-950' : 'bg-white text-zinc-700 border-zinc-300'"
                            x-text="continuousAutoPrint ? 'AUTOMÁTICA (ON)' : 'MANUAL EN LOTE'"></button>
                </div>
                <i class="fa-solid fa-scroll text-orange-500 text-base"></i>
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- 2. LECTOR QR ARRIBA (5/12) + ETIQUETA LÁSER Y COLA CONTINUA (7/12)    -->
    <!-- ===================================================================== -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

        <!-- Columna Izquierda (5/12): Escáner QR Proveedor (Arriba) + Checklist Normativo -->
        <div class="lg:col-span-5 space-y-5">
            <!-- Visor Óptico QR + Entrada Directa de Pistola Láser/QR -->
            <div class="bg-white border-2 border-zinc-950 rounded-lg p-4 space-y-3 shadow-2xs">
                <div class="flex items-center justify-between border-b border-zinc-200 pb-2">
                    <span class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                        1. Escáner de Etiqueta QR del Proveedor
                    </span>
                    <span class="text-[10px] font-mono text-zinc-500">Cámara o Pistola Lectora</span>
                </div>

                <div class="relative w-full max-w-[280px] aspect-square mx-auto bg-zinc-950 rounded-md border border-zinc-300 overflow-hidden flex flex-col items-center justify-center">
                    <div id="handheld-qr-reader" class="absolute inset-0 w-full h-full"></div>

                    <div x-show="cameraState === 'scanning'" x-cloak class="pointer-events-none absolute inset-0 flex items-center justify-center">
                        <div class="w-44 h-44 relative">
                            <span class="absolute top-0 left-0 w-5 h-5 border-t-2 border-l-2 border-orange-500"></span>
                            <span class="absolute top-0 right-0 w-5 h-5 border-t-2 border-r-2 border-orange-500"></span>
                            <span class="absolute bottom-0 left-0 w-5 h-5 border-b-2 border-l-2 border-orange-500"></span>
                            <span class="absolute bottom-0 right-0 w-5 h-5 border-b-2 border-r-2 border-orange-500"></span>
                        </div>
                    </div>

                    <div x-show="cameraState !== 'scanning'" class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center bg-zinc-950 text-white">
                        <div class="w-28 h-28 relative flex items-center justify-center mb-3">
                            <span class="absolute top-0 left-0 w-5 h-5 border-t-2 border-l-2 border-orange-500"></span>
                            <span class="absolute top-0 right-0 w-5 h-5 border-t-2 border-r-2 border-orange-500"></span>
                            <span class="absolute bottom-0 left-0 w-5 h-5 border-b-2 border-l-2 border-orange-500"></span>
                            <span class="absolute bottom-0 right-0 w-5 h-5 border-b-2 border-r-2 border-orange-500"></span>
                            <i class="fa-solid fa-qrcode text-4xl text-zinc-500" x-show="cameraState === 'idle'"></i>
                            <i class="fa-solid fa-spinner fa-spin text-3xl text-orange-500" x-show="cameraState === 'loading'" x-cloak></i>
                        </div>
                        <p class="text-xs font-semibold text-zinc-200"
                           x-text="cameraState === 'loading' ? 'Procesando cámara / QR...' : 'Escanea el QR integrado enviado por el proveedor'"></p>
                    </div>
                </div>

                <div x-show="cameraError !== ''" x-cloak class="p-2.5 rounded bg-red-50 border border-red-300 text-xs text-red-900" x-text="cameraError"></div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <button x-show="cameraState !== 'scanning'"
                            type="button"
                            @click="startCamera()"
                            :disabled="!palletCompliant || cameraState === 'loading'"
                            class="w-full bg-orange-500 hover:bg-orange-600 disabled:bg-zinc-300 text-white font-bold text-xs uppercase tracking-wider py-2.5 px-3 rounded transition cursor-pointer flex items-center justify-center space-x-2">
                        <i class="fa-solid fa-video"></i>
                        <span>Cámara en Vivo</span>
                    </button>

                    <button x-show="cameraState === 'scanning'" x-cloak
                            type="button"
                            @click="stopCamera()"
                            class="w-full bg-zinc-950 text-white font-bold text-xs uppercase tracking-wider py-2.5 px-3 rounded transition cursor-pointer flex items-center justify-center space-x-2">
                        <i class="fa-solid fa-stop"></i>
                        <span>Detener Lector</span>
                    </button>

                    <label class="w-full bg-zinc-950 hover:bg-zinc-800 text-white font-bold text-xs uppercase tracking-wider py-2.5 px-3 rounded transition cursor-pointer flex items-center justify-center space-x-2 text-center">
                        <i class="fa-solid fa-camera text-orange-400"></i>
                        <span>Foto Cámara Móvil</span>
                        <input type="file"
                               accept="image/*"
                               capture="environment"
                               @change="scanQrPhoto($event)"
                               class="hidden">
                    </label>
                </div>

                <!-- Lectura rápida con pistola USB / JSON de etiqueta integrada -->
                <div class="pt-2 border-t border-zinc-200 space-y-1.5 font-mono text-xs">
                    <label class="block text-[10px] font-bold uppercase text-zinc-600">Entrada de Pistola Lectora / JSON QR Proveedor</label>
                    <div class="flex gap-1.5">
                        <input type="text"
                               x-model="manualQrInput"
                               @keydown.enter.prevent="submitManualQr()"
                               placeholder='{"bulto":"BLT-...","destino":"SUC-02","sku":"PAP-007","cajas":1}'
                               class="flex-1 bg-zinc-50 border border-zinc-300 rounded px-2.5 py-1.5 text-[11px] text-zinc-900 focus:bg-white focus:outline-none focus:border-orange-500">
                        <button type="button"
                                @click="submitManualQr()"
                                :disabled="scanningBusy || manualQrInput.trim() === ''"
                                class="px-3 py-1.5 rounded bg-zinc-950 hover:bg-orange-500 disabled:bg-zinc-300 text-white text-[11px] font-bold uppercase cursor-pointer">
                            Validar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Checklist Normativo -->
            <div class="bg-white border border-zinc-200 rounded-lg p-4 space-y-3 shadow-2xs">
                <div class="flex items-center justify-between border-b border-zinc-200 pb-2">
                    <span class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                        2. Checklist Físico de Recepción
                    </span>
                    <span class="font-mono text-[10px] font-bold px-2 py-0.5 rounded border"
                          :class="palletCompliant ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : 'bg-amber-50 border-amber-300 text-amber-800'"
                          x-text="palletCompliant ? 'NORMA OK' : 'INCOMPLETO'"></span>
                </div>

                <div class="grid grid-cols-3 gap-2 text-xs font-semibold">
                    <button type="button"
                            @click="checkBase = !checkBase"
                            :class="checkBase ? 'bg-zinc-950 text-white border-zinc-950' : 'bg-zinc-50 text-zinc-500 border-zinc-300'"
                            class="py-2 px-2 rounded border text-center transition cursor-pointer">
                        <div class="font-mono text-[10px] font-bold" x-text="checkBase ? '✓ OK' : 'PEND'"></div>
                        <div class="mt-0.5 text-[11px]">Base 1.12×1.15m</div>
                    </button>

                    <button type="button"
                            @click="checkFilm = !checkFilm"
                            :class="checkFilm ? 'bg-zinc-950 text-white border-zinc-950' : 'bg-zinc-50 text-zinc-500 border-zinc-300'"
                            class="py-2 px-2 rounded border text-center transition cursor-pointer">
                        <div class="font-mono text-[10px] font-bold" x-text="checkFilm ? '✓ OK' : 'PEND'"></div>
                        <div class="mt-0.5 text-[11px]">Etiqueta Íntegra</div>
                    </button>

                    <button type="button"
                            @click="checkHeight = !checkHeight"
                            :class="checkHeight ? 'bg-zinc-950 text-white border-zinc-950' : 'bg-zinc-50 text-zinc-500 border-zinc-300'"
                            class="py-2 px-2 rounded border text-center transition cursor-pointer">
                        <div class="font-mono text-[10px] font-bold" x-text="checkHeight ? '✓ OK' : 'PEND'"></div>
                        <div class="mt-0.5 text-[11px]">&le; 1.85 m</div>
                    </button>
                </div>
            </div>
        </div>

        <!-- Columna Derecha (7/12): Etiqueta #2 Generada al Comprobar + Rollo Continuo Láser -->
        <div class="lg:col-span-7 space-y-5">

            <!-- Segunda Etiqueta Interna Generada Automáticamente (Acomodo + Origen + Salida) -->
            <div class="bg-white border-2 border-zinc-950 rounded-lg overflow-hidden shadow-2xs">
                <div class="bg-zinc-950 text-white px-5 py-3 flex items-center justify-between">
                    <div>
                        <span class="px-2 py-0.5 rounded bg-orange-500 text-zinc-950 font-mono text-[10px] font-extrabold uppercase">
                            ETIQUETA #2 · INTERNA DEL OPERADOR (LÁSER CONTINUA)
                        </span>
                        <h3 class="text-xs font-extrabold uppercase tracking-wider mt-1">
                            Etiqueta de Ubicación de Acomodo, Lugar de Origen y Ruta de Salida
                        </h3>
                    </div>
                    <template x-if="latestLaserLabel">
                        <a :href="latestLaserLabel.print_url" target="_blank"
                           class="px-2.5 py-1 rounded bg-white hover:bg-zinc-200 text-zinc-950 font-mono text-[10px] font-extrabold uppercase">
                            <i class="fa-solid fa-print mr-1"></i> Imprimir Esta
                        </a>
                    </template>
                </div>

                <div class="p-5">
                    <template x-if="scanResult !== null && !scanResult.accepted">
                        <div class="p-4 rounded-md bg-red-50 border-2 border-red-400 text-red-950 space-y-1 mb-3">
                            <div class="text-xs font-extrabold uppercase text-red-800">✕ COMPROBACIÓN RECHAZADA · NO SE GENERA ETIQUETA DE ACOMODO</div>
                            <div class="text-sm font-bold font-mono" x-text="scanResult.reason"></div>
                            <p class="text-xs text-red-700">Separe el artículo en área de cuarentena/devolución de andén.</p>
                        </div>
                    </template>

                    <template x-if="latestLaserLabel !== null">
                        <div class="border-2 border-zinc-950 rounded p-4 space-y-3 font-mono text-xs bg-white">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b-2 border-zinc-950 pb-2">
                                <div>
                                    <span class="text-[10px] font-bold uppercase text-emerald-700">✓ ARTÍCULO COMPROBADO · ETIQUETA LÁSER GENERADA</span>
                                    <div class="text-base font-extrabold text-zinc-950"
                                         x-text="latestLaserLabel.internal_label_code + ' · ' + latestLaserLabel.sku + ' (' + latestLaserLabel.boxes + ' caja/s · ' + latestLaserLabel.weight_kg + ' kg)'"></div>
                                </div>
                                <span class="px-2 py-0.5 bg-zinc-950 text-white text-[10px] font-bold"
                                      x-text="'QR PROV: ' + latestLaserLabel.scanned_bulto"></span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-stretch">
                                <template x-if="latestLaserLabel.qr_data_uri">
                                    <div class="sm:col-span-3 border-2 border-zinc-950 p-2 flex flex-col items-center justify-center text-center">
                                        <img :src="latestLaserLabel.qr_data_uri" alt="QR Interno Láser" class="w-24 h-24">
                                        <span class="text-[9px] font-bold mt-1" x-text="latestLaserLabel.internal_label_code"></span>
                                    </div>
                                </template>

                                <div :class="latestLaserLabel.qr_data_uri ? 'sm:col-span-9' : 'sm:col-span-12'" class="space-y-2">
                                    <!-- 1. Dónde debe ir acomodado -->
                                    <div class="bg-zinc-950 text-white p-2.5 rounded-xs flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <span class="text-[9px] uppercase text-orange-400 font-bold block">1. DÓNDE DEBE IR ACOMODADO</span>
                                            <strong class="text-sm font-extrabold" x-text="latestLaserLabel.rack_coordinate"></strong>
                                            <span class="text-[11px] text-zinc-300 ml-1" x-text="'· Tarima ' + latestLaserLabel.pallet_code"></span>
                                        </div>
                                        <span class="px-2 py-0.5 bg-white text-zinc-950 text-[10px] font-extrabold uppercase"
                                              x-text="'Piso ' + latestLaserLabel.zone_priority + ' · ' + latestLaserLabel.stow_zone + ' (Cama ' + latestLaserLabel.layer_index + ')'"></span>
                                    </div>

                                    <!-- 2. Lugar de Origen y 3. Lugar de Salida -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <div class="border border-zinc-950 p-2 bg-zinc-50">
                                            <span class="text-[9px] font-bold uppercase text-zinc-500 block">2. LUGAR DE ORIGEN (ENTRADA)</span>
                                            <div class="font-sans font-bold text-zinc-950 truncate" x-text="latestLaserLabel.origin_supplier"></div>
                                            <div class="text-[10px] text-zinc-700" x-text="'Andén ' + latestLaserLabel.origin_dock + ' · ' + latestLaserLabel.origin_folio"></div>
                                        </div>

                                        <div class="border-2 border-zinc-950 p-2 bg-white">
                                            <span class="text-[9px] font-bold uppercase text-zinc-500 block">3. LUGAR DE SALIDA (AL ENVIARLO)</span>
                                            <div class="font-sans font-extrabold text-zinc-950" x-text="latestLaserLabel.outbound_branch"></div>
                                            <div class="text-[10px] font-bold text-orange-600" x-text="latestLaserLabel.outbound_dock_target"></div>
                                            <div class="text-[10px] text-zinc-600" x-text="latestLaserLabel.outbound_cutoff"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div x-show="latestLaserLabel === null && (scanResult === null || scanResult.accepted)"
                         class="py-8 text-center border border-dashed border-zinc-300 rounded-md text-xs font-mono text-zinc-500">
                        Escanea el QR del proveedor o comprueba cualquier caja de la tabla de llegadas programadas para emitir su etiqueta láser de acomodo, origen y salida.
                    </div>
                </div>
            </div>

            <!-- Cola Continua de Etiquetas Láser de los Operadores -->
            <div class="bg-white border border-zinc-200 rounded-lg overflow-hidden shadow-2xs">
                <div class="px-5 py-3.5 border-b border-zinc-200 bg-zinc-50/60 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                            Cola Continua de Etiquetas Láser del Operador (<span x-text="laserQueue.length"></span>)
                        </h3>
                        <p class="text-[11px] text-zinc-500">
                            Secuencia lista para impresión láser continua conforme se comprueba cada artículo recibido.
                        </p>
                    </div>
                    <a :href="continuousSheetUrl" target="_blank"
                       class="px-3 py-1.5 rounded bg-orange-500 hover:bg-orange-600 text-white font-mono text-[11px] font-bold uppercase">
                        <i class="fa-solid fa-print mr-1"></i> Abrir Tira Continua Láser ↗
                    </a>
                </div>

                <div x-show="laserQueue.length === 0" class="py-6 text-center font-mono text-xs text-zinc-500">
                    Aún no hay etiquetas en la tira láser continua de este turno.
                </div>

                <div x-show="laserQueue.length > 0" class="overflow-x-auto max-h-64 overflow-y-auto">
                    <table class="w-full text-left text-xs border-collapse font-mono tabular-nums">
                        <thead class="sticky top-0">
                            <tr class="bg-zinc-950 text-white text-[10px] uppercase">
                                <th class="py-2 px-3">Folio Láser</th>
                                <th class="py-2 px-3">Artículo</th>
                                <th class="py-2 px-3">Dónde Acomodar</th>
                                <th class="py-2 px-3">Origen → Salida</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            <template x-for="lbl in laserQueue" :key="lbl.pallet_item_id">
                                <tr @click="latestLaserLabel = lbl"
                                    class="even:bg-zinc-50/80 hover:bg-orange-50/40 cursor-pointer transition">
                                    <td class="py-2 px-3 whitespace-nowrap">
                                        <strong class="text-zinc-950" x-text="lbl.internal_label_code"></strong>
                                        <span class="block text-[10px] text-zinc-500" x-text="lbl.verified_at"></span>
                                    </td>
                                    <td class="py-2 px-3">
                                        <strong class="text-zinc-950" x-text="lbl.sku"></strong>
                                        <span class="block text-[10px] text-zinc-600" x-text="lbl.boxes + ' cja · ' + lbl.weight_kg + ' kg'"></span>
                                    </td>
                                    <td class="py-2 px-3">
                                        <strong class="text-orange-600" x-text="lbl.rack_coordinate"></strong>
                                        <span class="block text-[10px] text-zinc-600" x-text="lbl.pallet_code + ' · Piso ' + lbl.zone_priority"></span>
                                    </td>
                                    <td class="py-2 px-3 text-[11px]">
                                        <div class="text-zinc-800" x-text="'Orig: Andén ' + lbl.origin_dock + ' (' + lbl.origin_supplier.slice(0, 18) + ')'"></div>
                                        <div class="font-bold text-zinc-950" x-text="'Sal: ' + lbl.outbound_branch + ' · ' + lbl.outbound_dock_target"></div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- 3. REGISTRO DE QUÉ VA A LLEGAR, DÓNDE (ANDÉN) Y CUÁNDO (HORARIO)      -->
    <!-- ===================================================================== -->
    <div class="bg-white border border-zinc-200 rounded-lg overflow-hidden shadow-2xs">
        <div class="px-5 py-3.5 border-b border-zinc-200 bg-zinc-50/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                    3. Registro Programado de Llegadas (Qué llega, Dónde [Andén R1–R4] y Cuándo)
                </h2>
                <p class="text-[11px] text-zinc-500">
                    Comprueba cada caja escaneando la etiqueta integrada del proveedor con la cámara arriba o con el botón directo de lectura por bulto.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-1.5 font-mono text-xs">
                <span class="text-[10px] font-bold uppercase text-zinc-500 mr-1">Andén:</span>
                <template x-for="dk in ['TODOS', 'R1', 'R2', 'R3', 'R4']" :key="dk">
                    <button type="button"
                            @click="dockFilter = dk"
                            :class="dockFilter === dk ? 'bg-zinc-950 text-white' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200'"
                            class="px-2.5 py-1 rounded text-[11px] font-bold transition cursor-pointer"
                            x-text="dk"></button>
                </template>
            </div>
        </div>

        <div x-show="filteredArrivals.length === 0" x-cloak class="py-10 text-center font-mono text-xs text-zinc-500">
            No hay llegadas pendientes para el filtro de andén seleccionado.
        </div>

        <div x-show="filteredArrivals.length > 0" class="overflow-x-auto max-h-80 overflow-y-auto">
            <table class="w-full text-left text-xs border-collapse font-mono tabular-nums">
                <thead class="sticky top-0 z-10">
                    <tr class="bg-zinc-950 text-white text-[10px] uppercase tracking-wider">
                        <th class="py-2.5 px-4">Dónde y Cuándo</th>
                        <th class="py-2.5 px-3">Lugar de Origen (Proveedor / Folio)</th>
                        <th class="py-2.5 px-3">Artículo Esperado (SKU)</th>
                        <th class="py-2.5 px-3">Destino Salida CDMX</th>
                        <th class="py-2.5 px-3">Comprobados</th>
                        <th class="py-2.5 px-4 text-right">Comprobación QR Proveedor</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    <template x-for="arr in filteredArrivals" :key="arr.id">
                        <tr class="even:bg-zinc-50/80 hover:bg-orange-50/40 transition">
                            <td class="py-2.5 px-4 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded bg-zinc-950 text-white text-[10px] font-bold" x-text="'ANDÉN ' + arr.dock_code"></span>
                                <div class="text-[11px] text-zinc-600 mt-0.5" x-text="arr.eta_window"></div>
                            </td>
                            <td class="py-2.5 px-3 font-sans">
                                <div class="font-bold text-zinc-950" x-text="arr.supplier_name"></div>
                                <div class="font-mono text-[11px] text-zinc-500" x-text="arr.folio"></div>
                            </td>
                            <td class="py-2.5 px-3 font-sans">
                                <div class="flex items-center space-x-1.5">
                                    <strong class="font-mono text-zinc-950" x-text="arr.sku"></strong>
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-mono bg-zinc-100 border border-zinc-300 text-zinc-700" x-text="arr.stow_zone"></span>
                                </div>
                                <div class="text-zinc-700 truncate max-w-xs" x-text="arr.product_name"></div>
                            </td>
                            <td class="py-2.5 px-3 font-sans">
                                <strong class="font-mono text-zinc-950" x-text="arr.branch_code + ' · ' + arr.branch_name"></strong>
                                <div class="font-mono text-[10px] text-orange-600 font-bold" x-text="arr.rack_code"></div>
                            </td>
                            <td class="py-2.5 px-3">
                                <strong class="text-zinc-950" x-text="arr.boxes_received + ' / ' + arr.boxes_expected + ' cajas'"></strong>
                                <div class="w-24 h-1.5 bg-zinc-200 rounded-xs overflow-hidden mt-1">
                                    <div class="h-full bg-emerald-600"
                                         :style="'width:' + Math.min(100, Math.round((arr.boxes_received / Math.max(1, arr.boxes_expected)) * 100)) + '%'"></div>
                                </div>
                            </td>
                            <td class="py-2.5 px-4 text-right whitespace-nowrap space-x-1">
                                <a :href="arr.supplier_label_url" target="_blank"
                                   title="Ver etiqueta integrada enviada por el proveedor"
                                   class="inline-block px-2 py-1 rounded border border-zinc-300 bg-white hover:border-zinc-950 text-zinc-700 text-[10px] font-bold uppercase">
                                    QR Prov ↗
                                </a>
                                <template x-if="arr.boxes_received < arr.boxes_expected">
                                    <button type="button"
                                            :disabled="scanningBusy || !palletCompliant"
                                            @click="scanNextArrivalBox(arr)"
                                            class="px-2.5 py-1 rounded bg-orange-500 hover:bg-orange-600 disabled:bg-zinc-300 text-white text-[10px] font-bold uppercase cursor-pointer">
                                        <i class="fa-solid fa-qrcode mr-1"></i>
                                        <span x-text="'Comprobar Caja #' + String(arr.boxes_received + 1).padStart(2, '0')"></span>
                                    </button>
                                </template>
                                <template x-if="arr.boxes_received >= arr.boxes_expected">
                                    <span class="px-2.5 py-1 rounded bg-emerald-50 border border-emerald-300 text-emerald-800 text-[10px] font-bold uppercase">
                                        ✓ Comprobado
                                    </span>
                                </template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('extra-scripts')
<script>
function handheldScanner(config) {
    return {
        csrfToken: config.csrfToken,
        scanUrl: config.scanUrl,
        laserSheetBaseUrl: config.laserSheetBaseUrl,
        arrivals: config.initialArrivals || [],
        laserQueue: config.initialLaserQueue || [],
        latestLaserLabel: (config.initialLaserQueue && config.initialLaserQueue.length > 0) ? config.initialLaserQueue[0] : null,
        dockFilter: 'TODOS',
        continuousAutoPrint: false,
        scanningBusy: false,
        manualQrInput: '',

        checkBase: true,
        checkFilm: true,
        checkHeight: true,
        cameraState: 'idle',
        cameraError: '',
        qrInstance: null,
        lastCode: '',
        scanResult: null,

        get palletCompliant() {
            return this.checkBase && this.checkFilm && this.checkHeight;
        },

        get filteredArrivals() {
            if (this.dockFilter === 'TODOS') {
                return this.arrivals;
            }
            return this.arrivals.filter(a => a.dock_code === this.dockFilter);
        },

        get totalExpectedBoxes() {
            return this.arrivals.reduce((acc, a) => acc + Number(a.boxes_expected || 0), 0);
        },

        get totalReceivedBoxes() {
            return this.arrivals.reduce((acc, a) => acc + Number(a.boxes_received || 0), 0);
        },

        get continuousSheetUrl() {
            if (this.laserQueue.length === 0) {
                return this.laserSheetBaseUrl;
            }
            const ids = this.laserQueue.map(l => l.pallet_item_id).join(',');
            return this.laserSheetBaseUrl + '?ids=' + ids;
        },

        beep(freq, durationMs) {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.frequency.value = freq;
                gain.gain.value = 0.12;
                osc.start();
                setTimeout(() => { osc.stop(); ctx.close(); }, durationMs);
            } catch (_) {}
        },

        async startCamera() {
            this.cameraError = '';
            this.cameraState = 'loading';
            try {
                await this.stopCamera();
                this.qrInstance = await window.CedisQrScanner.startLiveCamera(
                    'handheld-qr-reader',
                    (decodedText) => this.handleDecodedQr(decodedText)
                );
                this.cameraState = 'scanning';
            } catch (err) {
                this.cameraState = 'idle';
                this.cameraError = err?.message || 'No fue posible abrir la cámara en vivo. Usa "Foto Cámara Móvil".';
            }
        },

        async stopCamera() {
            if (this.qrInstance) {
                try {
                    if (this.cameraState === 'scanning') {
                        await this.qrInstance.stop();
                    }
                    this.qrInstance.clear();
                } catch (_) {}
                this.qrInstance = null;
            }
            this.cameraState = 'idle';
        },

        async scanQrPhoto(event) {
            const file = event.target?.files?.[0];
            if (!file) return;
            this.cameraError = '';
            this.cameraState = 'loading';
            try {
                await this.stopCamera();
                const decoded = await window.CedisQrScanner.decodeImageFile(file);
                await this.handleDecodedQr(decoded);
            } catch (_) {
                this.beep(220, 240);
                this.cameraError = 'No se detectó un código QR nítido en la foto. Acerca la cámara al QR con buena luz e intenta de nuevo.';
            } finally {
                this.cameraState = 'idle';
                if (event.target) {
                    event.target.value = '';
                }
            }
        },

        async submitManualQr() {
            const raw = this.manualQrInput.trim();
            if (!raw) return;
            await this.handleDecodedQr(raw);
            if (this.scanResult && this.scanResult.accepted) {
                this.manualQrInput = '';
            }
        },

        async scanNextArrivalBox(arr) {
            const nextSeq = Number(arr.boxes_received || 0) + 1;
            const bultoCode = 'BLT-ARR' + arr.id + '-' + arr.sku + '-' + String(nextSeq).padStart(2, '0');
            const payload = JSON.stringify({
                bulto: bultoCode,
                arrival_id: arr.id,
                folio: arr.folio,
                proveedor: arr.supplier_name,
                anden_entrada: arr.dock_code,
                destino: arr.branch_code,
                sku: arr.sku,
                cajas: 1,
                piezas: arr.master_factor,
                peso_kg: arr.master_weight_kg,
                zona: arr.stow_zone
            });
            await this.handleDecodedQr(payload);
        },

        async handleDecodedQr(rawText) {
            if (rawText === this.lastCode || this.scanningBusy) return;
            this.lastCode = rawText;
            setTimeout(() => { this.lastCode = ''; }, 1200);

            this.scanningBusy = true;
            try {
                const res = await fetch(this.scanUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken
                    },
                    body: JSON.stringify({ qr_payload: rawText })
                });

                const data = await res.json();
                if (!res.ok) {
                    this.beep(220, 240);
                    this.scanResult = {
                        accepted: false,
                        reason: data.message || 'Etiqueta QR rechazada en validación.'
                    };
                    return;
                }

                this.beep(880, 120);
                this.scanResult = { accepted: true };

                if (data.arrival_updated) {
                    const idx = this.arrivals.findIndex(a => Number(a.id) === Number(data.arrival_updated.id));
                    if (idx !== -1) {
                        this.arrivals[idx].boxes_received = data.arrival_updated.boxes_received;
                        this.arrivals[idx].status = data.arrival_updated.status;
                    }
                }

                if (Array.isArray(data.laser_labels) && data.laser_labels.length > 0) {
                    this.latestLaserLabel = data.laser_labels[0];
                    this.laserQueue = [...data.laser_labels, ...this.laserQueue];

                    if (this.continuousAutoPrint && data.continuous_print_url) {
                        window.open(data.continuous_print_url, '_blank');
                    }
                }
            } catch (_) {
                this.beep(220, 240);
                this.scanResult = {
                    accepted: false,
                    reason: 'Error de red al comprobar la etiqueta QR.'
                };
            } finally {
                this.scanningBusy = false;
            }
        }
    };
}
</script>
@endsection
