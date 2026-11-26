@extends('layouts.app')

@section('title', 'Portal de Proveedores · Avisos de Entrega (ASN) — Dokra')
@section('module-code', '04 · PROVEEDOR EXTERNO · AVISOS DE ENTREGA (ASN), CITAS Y ETIQUETAS QR')

@section('content')
@php
    $formattedArrivals = $scheduledArrivals->map(static fn ($a) => [
        'id' => $a->id,
        'folio' => $a->folio,
        'branch_id' => $a->branch_id,
        'branch_code' => $a->branch?->code ?? 'SUC-01',
        'branch_name' => $a->branch?->name ?? 'Centro Histórico',
        'branch_zone' => $a->branch?->zone ?? 'Centro',
        'rack_code' => $a->branch?->rack_code ?? 'RACK-01',
        'product_id' => $a->product_id,
        'sku' => $a->product?->sku ?? 'PAP-007',
        'product_name' => $a->product?->name ?? 'Producto Papelería',
        'category' => $a->product?->category ?? 'Papel y cuadernos',
        'presentation' => $a->product?->commercial_presentation ?? '1 pieza',
        'master_factor' => $a->product?->master_factor ?? 10,
        'master_weight_kg' => (float) ($a->product?->master_weight_kg ?? 10),
        'master_volume_m3' => (float) ($a->product?->master_volume_m3 ?? 0.02),
        'master_density' => $a->product?->master_density_kg_m3 ?? 500,
        'ect_rating' => $a->product?->ect_rating ?? 32,
        'max_layers' => $a->product?->max_layers_operational ?? 5,
        'stow_zone' => $a->product?->stow_zone ?? 'Base / Pesado',
        'orientation_rule' => $a->product?->orientation_rule ?? 'ESTANDAR',
        'boxes_expected' => $a->boxes_expected,
        'boxes_received' => $a->boxes_received,
        'total_pieces' => $a->boxes_expected * ($a->product?->master_factor ?? 1),
        'total_weight_kg' => round($a->boxes_expected * (float) ($a->product?->master_weight_kg ?? 1), 2),
        'total_volume_m3' => round($a->boxes_expected * (float) ($a->product?->master_volume_m3 ?? 0.02), 3),
        'dock_code' => $a->dock_code,
        'eta_window' => $a->eta_window,
        'supplier_name' => $a->supplier_name,
        'supplier_rfc' => 'DVE940812KM3',
        'contact_name' => 'Lic. Adriana Morales Vega',
        'contact_phone' => '55 5340 8910',
        'contact_email' => 'logistica@distribuidorametropolitana.com.mx',
        'status' => $a->status === 'received' ? 'COMPLETA' : ($a->status === 'partial' ? 'EN RECEPCIÓN' : 'PROGRAMADA'),
        'qr_label_url' => route('wms.qr.label', ['arrival_id' => $a->id], false),
        'acomodo_url' => route('wms.index', ['branch' => $a->branch?->code ?? 'SUC-01'], false),
    ])->values();
@endphp

<div x-data="supplierPortalApp({
        csrfToken: @js(csrf_token()),
        products: @js($products),
        branches: @js($branches),
        initialArrivals: @js($formattedArrivals),
        scheduleUrl: @js(route('wms.supplier.schedule', [], false)),
        productsStoreUrl: @js(route('wms.products.store', [], false)),
        qrLabelBaseUrl: @js(route('wms.qr.label', [], false)),
        defaultSupplierRfc: @js($user->supplier_rfc ?? 'DVE940812KM3'),
     })"
     class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

    <!-- ===================================================================== -->
    <!-- 1. BARRA SUPERIOR ADMINISTRATIVA (TÍTULO, KPIS Y ACCIÓN PRINCIPAL)    -->
    <!-- ===================================================================== -->
    <div class="bg-white border border-zinc-200 rounded-lg p-5 shadow-2xs">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-4 border-b border-zinc-200">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-2 py-0.5 text-[10px] font-mono font-bold uppercase tracking-widest bg-zinc-950 text-white rounded">
                        ERP PROVEEDORES · Dokra
                    </span>
                    <span class="px-2 py-0.5 text-[10px] font-mono font-semibold bg-zinc-100 border border-zinc-300 text-zinc-700 rounded">
                        RFC: {{ $user->supplier_rfc ?? 'DVE940812KM3' }}
                    </span>
                    <span class="px-2 py-0.5 text-[10px] font-mono font-bold bg-emerald-50 border border-emerald-300 text-emerald-800 rounded">
                        ● PROVEEDOR ACTIVO
                    </span>
                </div>
                <h1 class="text-xl sm:text-2xl font-extrabold text-zinc-950 tracking-tight mt-1">
                    Registro de Artículos y Emisión de Etiquetas Integradas de Envío
                </h1>
                <p class="text-xs text-zinc-500 mt-0.5">
                    Todo artículo registrado genera automáticamente su <strong>Etiqueta QR Integrada</strong> obligatoria para envío a los andenes R1–R4 del Dokra.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                <button type="button"
                        @click="openNewProductModal()"
                        class="px-3.5 py-2 rounded-md bg-zinc-950 hover:bg-zinc-800 text-white text-xs font-bold uppercase tracking-wider transition cursor-pointer flex items-center space-x-1.5">
                    <i class="fa-solid fa-box-open text-orange-400"></i>
                    <span>+ Nuevo Producto a Almacenar</span>
                </button>

                <button type="button"
                        @click="openSupplierCard()"
                        class="px-3.5 py-2 rounded-md border border-zinc-300 bg-white hover:border-zinc-950 text-zinc-800 text-xs font-bold uppercase tracking-wider transition cursor-pointer flex items-center space-x-1.5">
                    <i class="fa-solid fa-address-card text-zinc-500"></i>
                    <span>Ficha del Proveedor</span>
                </button>

                <button type="button"
                        @click="showFormPanel = !showFormPanel"
                        class="px-4 py-2 rounded-md bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold uppercase tracking-wider transition cursor-pointer flex items-center space-x-1.5 shadow-2xs">
                    <i class="fa-solid" :class="showFormPanel ? 'fa-chevron-up' : 'fa-plus'"></i>
                    <span x-text="showFormPanel ? 'Ocultar Formulario de Registro' : 'Registrar Nueva Entrega / Cita QR'"></span>
                </button>
            </div>
        </div>

        <!-- Resumen Ejecutivo de Oficina (4 Indicadores Compactos) -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-4 font-mono tabular-nums text-xs">
            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block">Partidas Programadas</span>
                    <strong class="text-base font-extrabold text-zinc-950" x-text="scheduledList.length + ' registros'"></strong>
                </div>
                <i class="fa-solid fa-clipboard-list text-zinc-400 text-base"></i>
            </div>

            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block">Cajas en Tránsito</span>
                    <strong class="text-base font-extrabold text-zinc-950" x-text="totalScheduledBoxes + ' cajas'"></strong>
                </div>
                <i class="fa-solid fa-boxes-stacked text-zinc-400 text-base"></i>
            </div>

            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block">Peso Programado</span>
                    <strong class="text-base font-extrabold text-zinc-950" x-text="Number(totalScheduledWeight).toLocaleString('es-MX') + ' kg'"></strong>
                </div>
                <i class="fa-solid fa-weight-hanging text-zinc-400 text-base"></i>
            </div>

            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block">Ventana Recibo</span>
                    <strong class="text-base font-extrabold text-zinc-950">08:00 – 18:00 H</strong>
                </div>
                <i class="fa-solid fa-clock text-orange-500 text-base"></i>
            </div>
        </div>
    </div>

    <!-- Avisos de Confirmación o Error -->
    <div x-show="successMessage !== ''" x-cloak
         class="p-4 rounded-lg bg-zinc-950 text-white border-l-4 border-orange-500 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm">
        <div class="text-xs">
            <div class="font-mono text-[10px] font-bold uppercase text-orange-400">✓ REGISTRO GUARDADO Y ETIQUETAS INTEGRADAS GENERADAS</div>
            <div class="font-medium mt-0.5" x-text="successMessage"></div>
        </div>
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <template x-if="createdArrivals.length > 0">
                <a :href="batchQrLabelUrl || createdArrivals[0].qr_label_url" target="_blank"
                   class="px-3 py-1.5 rounded bg-orange-500 hover:bg-orange-600 text-white text-xs font-mono font-bold uppercase">
                    <i class="fa-solid fa-print mr-1"></i> Imprimir Conjunto Completo (1 Principal + 1 por Caja)
                </a>
            </template>
            @if(Auth::check() && Auth::user()->isGerente())
            <a href="{{ route('wms.index') }}"
               class="px-3 py-1.5 rounded bg-white hover:bg-zinc-200 text-zinc-950 text-xs font-mono font-bold uppercase">
                Ir a Motor de Acomodo →
            </a>
            @endif
            <button type="button" @click="successMessage = ''" class="text-zinc-400 hover:text-white px-1 cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </div>

    <!-- Panel Automático de Etiquetas Integradas Generadas al Registrar Artículos -->
    <div x-show="createdArrivals.length > 0" x-cloak
         class="bg-white border-2 border-zinc-950 rounded-lg overflow-hidden shadow-sm">
        <div class="bg-zinc-950 text-white px-5 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <span class="px-2 py-0.5 rounded bg-orange-500 text-zinc-950 font-mono text-[10px] font-extrabold uppercase">
                    ETIQUETA #1 · INTEGRADA DE ORIGEN (OBLIGATORIA EN CADA CAJA ENVIADA)
                </span>
                <h2 class="text-xs font-extrabold uppercase tracking-wider mt-1">
                    Etiquetas QR Integradas Generadas Automáticamente para Adhesión en Origen
                </h2>
            </div>
            <span class="font-mono text-[11px] text-emerald-400">
                ● Notificado en vivo a Operadores de Recepción Dokra
            </span>
        </div>

        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
            <template x-for="arr in createdArrivals" :key="arr.id">
                <div class="border-2 border-zinc-950 rounded-md p-4 bg-zinc-50 flex flex-col sm:flex-row items-center gap-4 font-mono text-xs">
                    <div class="border border-zinc-950 bg-white p-2 text-center shrink-0">
                        <img :src="arr.sample_qr_svg" :alt="'QR ' + arr.sample_bulto_code" class="w-28 h-28 mx-auto">
                        <span class="block text-[9px] font-bold text-zinc-700 mt-1" x-text="arr.sample_bulto_code"></span>
                    </div>
                    <div class="flex-1 space-y-1.5 w-full">
                        <div class="text-[10px] font-bold uppercase text-orange-600" x-text="'FOLIO: ' + arr.folio + ' · ANDÉN ' + arr.dock_code"></div>
                        <div class="font-sans text-sm font-extrabold text-zinc-950" x-text="arr.sku + ' · ' + arr.product_name"></div>
                        <div class="text-[11px] text-zinc-700">
                            Destino: <strong class="text-zinc-950" x-text="arr.branch_code + ' · ' + arr.branch_name + ' (' + arr.rack_code + ')'"></strong>
                        </div>
                        <div class="text-[11px] text-zinc-600">
                            Cita: <strong x-text="arr.eta_window"></strong> · Lote: <strong x-text="arr.boxes_expected + ' cajas (' + arr.total_weight_kg + ' kg)'"></strong>
                        </div>
                        <div class="pt-1.5">
                            <a :href="arr.qr_label_url" target="_blank"
                               class="inline-flex items-center px-3 py-1.5 rounded bg-orange-500 hover:bg-orange-600 text-white text-[11px] font-bold uppercase">
                                <i class="fa-solid fa-qrcode mr-1.5"></i> Imprimir Etiquetas Integradas (<span x-text="arr.boxes_expected"></span> cajas)
                            </a>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <div x-show="errorMessage !== ''" x-cloak
         class="p-3.5 rounded-md bg-red-950 text-white border-l-4 border-red-500 text-xs flex items-center justify-between">
        <span><i class="fa-solid fa-triangle-exclamation text-red-400 mr-1.5"></i> <span x-text="errorMessage"></span></span>
        <button type="button" @click="errorMessage = ''" class="text-red-300 hover:text-white cursor-pointer"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <!-- ===================================================================== -->
    <!-- 2. FORMULARIO ADMINISTRATIVO EN CUADRÍCULA ORDENADA POR BLOQUES       -->
    <!-- ===================================================================== -->
    <div x-show="showFormPanel" x-cloak class="bg-white border-2 border-zinc-950 rounded-lg overflow-hidden shadow-sm">
        <!-- Encabezado del Formulario -->
        <div class="bg-zinc-950 text-white px-5 py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div class="flex items-center space-x-2.5">
                <span class="w-6 h-6 rounded bg-orange-500 text-zinc-950 font-mono text-xs font-extrabold flex items-center justify-center">
                    <i class="fa-solid fa-file-signature"></i>
                </span>
                <div>
                    <h2 class="text-sm font-extrabold uppercase tracking-wider">
                        Formulario de Registro de Entrega y Agendamiento de Andén
                    </h2>
                </div>
            </div>
            <div class="flex items-center space-x-3 font-mono text-[11px]">
                <span :class="isFolioValid ? 'text-emerald-400' : 'text-orange-400'"
                      x-text="isFolioValid ? '✓ Aviso de entrega válido' : '● Verificar remisión'"></span>
                <span class="text-zinc-600">|</span>
                <span :class="!hasScheduleConflict ? 'text-emerald-400' : 'text-red-400'"
                      x-text="!hasScheduleConflict ? ('✓ Andén ' + selectedDock + ' disponible') : '✕ Conflicto de horario'"></span>
            </div>
        </div>

        <div class="p-5 space-y-6">
            <!-- BLOQUE 1 y BLOQUE 2 en Cuadrícula de 12 Columnas -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                <!-- Bloque 1 (6/12): Datos de Remisión y Aviso de Entrega (ASN) -->
                <div class="lg:col-span-6 bg-zinc-50 border border-zinc-200 rounded-md p-4 space-y-3">
                    <div class="flex items-center justify-between border-b border-zinc-200 pb-2">
                        <span class="text-[11px] font-extrabold uppercase tracking-wider text-zinc-900">
                            1. Aviso de Entrega (ASN) y Remisión de Proveedor
                        </span>
                        <span class="text-[10px] font-mono text-zinc-500">1 Remisión única por envío</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Folio de Envío / Aviso (ASN)</label>
                            <input type="text"
                                   x-model="asnFolio"
                                   placeholder="Ej. ASN-2026-0105"
                                   class="w-full bg-white border border-zinc-300 rounded px-3 py-2 font-mono font-bold text-zinc-950 focus:outline-none focus:border-orange-500">
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Folio de Remisión de Proveedor</label>
                            <input type="text"
                                   x-model="remissionFolio"
                                   placeholder="Ej. REM-DM-99104"
                                   class="w-full bg-white border rounded px-3 py-2 font-mono uppercase text-zinc-950 focus:outline-none"
                                   :class="isDuplicateFolio ? 'border-red-600 ring-1 ring-red-600' : 'border-zinc-300 focus:border-orange-500'">
                            <p x-show="isDuplicateFolio" x-cloak class="text-[10px] font-bold text-red-600 mt-0.5">
                                Este folio de remisión ya fue registrado previamente.
                            </p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Razón Social del Proveedor</label>
                            <input type="text"
                                   x-model="supplierName"
                                   class="w-full bg-white border border-zinc-300 rounded px-3 py-2 text-zinc-950 focus:outline-none focus:border-orange-500">
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Contacto de Logística</label>
                            <input type="text"
                                   x-model="contactName"
                                   class="w-full bg-white border border-zinc-300 rounded px-3 py-1.5 text-zinc-800 focus:outline-none focus:border-orange-500">
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Teléfono / Correo</label>
                            <input type="text"
                                   x-model="contactPhone"
                                   class="w-full bg-white border border-zinc-300 rounded px-3 py-1.5 font-mono text-zinc-800 focus:outline-none focus:border-orange-500">
                        </div>
                    </div>
                </div>

                <!-- Bloque 2 (6/12): Programación de Andén y Ventana Horaria -->
                <div class="lg:col-span-6 bg-zinc-50 border border-zinc-200 rounded-md p-4 space-y-3">
                    <div class="flex items-center justify-between border-b border-zinc-200 pb-2">
                        <span class="text-[11px] font-extrabold uppercase tracking-wider text-zinc-900">
                            2. Programación de Cita en Andén (R1–R4)
                        </span>
                        <span class="text-[10px] font-mono text-zinc-500">Horario 08:00 a 18:00 h</span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs">
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Fecha Arribo</label>
                            <input type="date"
                                   x-model="selectedDate"
                                   class="w-full bg-white border border-zinc-300 rounded px-2.5 py-1.5 font-mono text-zinc-950 focus:outline-none focus:border-orange-500">
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Andén Recibo</label>
                            <select x-model="selectedDock"
                                    class="w-full bg-white border border-zinc-300 rounded px-2.5 py-1.5 font-mono font-bold text-zinc-950 focus:outline-none focus:border-orange-500">
                                <option value="R1">Andén R1</option>
                                <option value="R2">Andén R2</option>
                                <option value="R3">Andén R3</option>
                                <option value="R4">Andén R4</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Hora Inicio</label>
                            <select x-model.number="startHour"
                                    class="w-full bg-white border border-zinc-300 rounded px-2.5 py-1.5 font-mono text-zinc-950 focus:outline-none focus:border-orange-500">
                                @for($h = 8; $h <= 17; $h++)
                                    <option value="{{ $h }}">{{ sprintf('%02d:00', $h) }}</option>
                                @endfor
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Duración</label>
                            <select x-model.number="durationHours"
                                    class="w-full bg-white border border-zinc-300 rounded px-2.5 py-1.5 font-mono text-zinc-950 focus:outline-none focus:border-orange-500">
                                <option value="1">1 hora</option>
                                <option value="2">2 horas</option>
                                <option value="3">3 horas</option>
                            </select>
                        </div>
                    </div>

                    <!-- Matriz Compacta Horizontal de Disponibilidad por Andén -->
                    <div class="pt-1">
                        <div class="flex items-center justify-between text-[10px] font-mono text-zinc-500 mb-1">
                            <span>Disponibilidad en <strong class="text-zinc-900" x-text="'Andén ' + selectedDock"></strong> (clic en hora libre):</span>
                            <span><span class="inline-block w-2 h-2 bg-orange-500 rounded-xs mr-1"></span>Tu Cita · <span class="inline-block w-2 h-2 bg-zinc-800 rounded-xs mx-1"></span>Ocupado</span>
                        </div>
                        <div class="grid grid-cols-5 sm:grid-cols-10 gap-1 font-mono text-[10px] tabular-nums">
                            @for($h = 8; $h <= 17; $h++)
                                <button type="button"
                                        @click="if (!isOccupied(selectedDock, {{ $h }})) { startHour = {{ $h }}; }"
                                        class="py-1.5 px-1 rounded border text-center transition cursor-pointer"
                                        :class="isOccupied(selectedDock, {{ $h }})
                                            ? 'bg-zinc-800 text-zinc-400 border-zinc-800 cursor-not-allowed'
                                            : (isInMyRange(selectedDock, {{ $h }})
                                                ? 'bg-orange-500 text-white border-orange-600 font-bold'
                                                : 'bg-white hover:bg-zinc-100 text-zinc-700 border-zinc-300')">
                                    <div>{{ sprintf('%02d:00', $h) }}</div>
                                    <div class="text-[8px]"
                                         x-text="isOccupied(selectedDock, {{ $h }}) ? 'OCUP' : (isInMyRange(selectedDock, {{ $h }}) ? 'CITA' : 'LIBRE')"></div>
                                </button>
                            @endfor
                        </div>
                    </div>
                </div>
            </div>

            <!-- BLOQUE 3: Tabla de Partidas a Entregar (Mono-SKU por Bulto) -->
            <div class="bg-zinc-50 border border-zinc-200 rounded-md p-4 space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-zinc-200 pb-2.5">
                    <div>
                        <span class="text-[11px] font-extrabold uppercase tracking-wider text-zinc-900">
                            3. Desglose de Productos y Destinos a Entregar (Regla Mono-SKU)
                        </span>
                        <span class="text-[11px] text-zinc-500 ml-2">
                            Selecciona un producto existente o da de alta uno nuevo para almacenarlo en el rack de su sucursal destino.
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 self-start">
                        <button type="button"
                                @click="openNewProductModal()"
                                class="px-3 py-1.5 rounded border border-zinc-950 bg-white hover:bg-zinc-100 text-zinc-950 text-[11px] font-bold uppercase tracking-wider transition cursor-pointer">
                            <i class="fa-solid fa-plus text-orange-500 mr-1"></i> Dar de Alta Nuevo Producto
                        </button>
                        <button type="button"
                                @click="addPackage()"
                                class="px-3 py-1.5 rounded bg-zinc-950 hover:bg-orange-600 text-white text-[11px] font-bold uppercase tracking-wider transition cursor-pointer">
                            + Agregar Fila de Producto
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto bg-white border border-zinc-300 rounded">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-zinc-100 border-b border-zinc-300 font-mono text-[10px] uppercase text-zinc-700">
                                <th class="py-2 px-3 w-12">#</th>
                                <th class="py-2 px-3">Producto de Papelería (SKU · Nombre · Peso Caja)</th>
                                <th class="py-2 px-3 w-56">Sucursal Destino (CDMX)</th>
                                <th class="py-2 px-3 w-28">Cajas Máster</th>
                                <th class="py-2 px-3 w-32">Total Piezas</th>
                                <th class="py-2 px-3 w-32">Peso / Volumen</th>
                                <th class="py-2 px-3 w-32">Zona Tarima</th>
                                <th class="py-2 px-3 w-16 text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 font-mono tabular-nums">
                            <template x-for="(pkg, idx) in packages" :key="idx">
                                <tr class="even:bg-zinc-50/70 hover:bg-orange-50/30">
                                    <td class="py-2 px-3 font-bold text-zinc-500" x-text="idx + 1"></td>
                                    <td class="py-2 px-3 font-sans">
                                        <select x-model.number="pkg.product_id"
                                                class="w-full bg-white border border-zinc-300 rounded px-2.5 py-1.5 text-xs font-mono text-zinc-950 focus:outline-none focus:border-orange-500">
                                            <template x-for="p in products" :key="p.id">
                                                <option :value="p.id" x-text="p.sku + ' · ' + p.name + ' (' + p.master_weight_kg + ' kg)'"></option>
                                            </template>
                                        </select>
                                    </td>
                                    <td class="py-2 px-3">
                                        <select x-model.number="pkg.branch_id"
                                                class="w-full bg-white border border-zinc-300 rounded px-2.5 py-1.5 text-xs font-mono text-zinc-950 focus:outline-none focus:border-orange-500">
                                            <template x-for="b in branches" :key="b.id">
                                                <option :value="b.id" x-text="b.code + ' · ' + b.name"></option>
                                            </template>
                                        </select>
                                    </td>
                                    <td class="py-2 px-3">
                                        <input type="number" min="1" max="500"
                                               x-model.number="pkg.boxes"
                                               class="w-20 bg-white border border-zinc-300 rounded px-2.5 py-1.5 text-xs font-bold text-zinc-950 focus:outline-none focus:border-orange-500">
                                    </td>
                                    <td class="py-2 px-3 text-zinc-800"
                                        x-text="(Number(pkg.boxes || 1) * Number(getProduct(pkg.product_id)?.master_factor || 1)).toLocaleString('es-MX') + ' uds'"></td>
                                    <td class="py-2 px-3">
                                        <strong class="text-zinc-950" x-text="(Number(pkg.boxes || 1) * Number(getProduct(pkg.product_id)?.master_weight_kg || 1)).toFixed(1) + ' kg'"></strong>
                                        <span class="block text-[10px] text-zinc-500" x-text="(Number(pkg.boxes || 1) * Number(getProduct(pkg.product_id)?.master_volume_m3 || 0.01)).toFixed(2) + ' m³'"></span>
                                    </td>
                                    <td class="py-2 px-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-zinc-100 border border-zinc-300 text-zinc-800"
                                              x-text="getProduct(pkg.product_id)?.stow_zone || ''"></span>
                                    </td>
                                    <td class="py-2 px-3 text-center">
                                        <button type="button"
                                                @click="removePackage(idx)"
                                                x-show="packages.length > 1"
                                                title="Eliminar fila"
                                                class="px-2 py-1 rounded text-zinc-400 hover:text-red-600 hover:bg-red-50 cursor-pointer">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Barra de Totales y Botón de Guardado -->
                <div class="pt-2 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="font-mono text-xs text-zinc-700 space-x-4">
                        <span>Partidas: <strong class="text-zinc-950" x-text="packages.length"></strong></span>
                        <span>Total Cajas: <strong class="text-zinc-950" x-text="totalBoxesCount"></strong></span>
                        <span>Peso Total: <strong class="text-zinc-950" x-text="totalWeightKg + ' kg'"></strong></span>
                    </div>

                    <div class="flex items-center space-x-2.5">
                        <button type="button"
                                @click="showFormPanel = false"
                                class="px-4 py-2.5 rounded border border-zinc-300 bg-white hover:bg-zinc-100 text-zinc-700 text-xs font-bold uppercase cursor-pointer">
                            Cerrar Panel
                        </button>
                        <button type="button"
                                @click="confirmSchedule()"
                                :disabled="!isFolioValid || hasScheduleConflict || submitting"
                                class="px-5 py-2.5 rounded bg-orange-500 hover:bg-orange-600 disabled:bg-zinc-300 disabled:text-zinc-500 text-white text-xs font-bold uppercase tracking-wider transition cursor-pointer flex items-center space-x-2">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span x-show="!submitting">Guardar Entrega y Generar Etiquetas QR</span>
                            <span x-show="submitting" x-cloak>Guardando registro...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(Auth::check() && (Auth::user()->isGerente() || Auth::user()->isJefeEmbarque()))
    <!-- ===================================================================== -->
    <!-- 3. BARRA DE BÚSQUEDA Y FILTROS RÁPIDOS DE OFICINA (SOLO GERENTE/EMB)  -->
    <!-- ===================================================================== -->
    <div class="bg-white border border-zinc-200 rounded-lg p-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <!-- Buscador General -->
        <div class="relative flex-1 max-w-md">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-zinc-400 text-xs"></i>
            <input type="text"
                   x-model="tableSearch"
                   placeholder="Buscar por Razón Social, Folio ASN, Remisión, SKU o Destino CDMX..."
                   class="w-full bg-zinc-50 border border-zinc-300 rounded-md pl-8 pr-16 py-1.5 text-xs text-zinc-950 focus:bg-white focus:outline-none focus:border-orange-500">
            <button x-show="tableSearch !== ''"
                    type="button"
                    @click="tableSearch = ''"
                    class="absolute right-2.5 top-1.5 text-[11px] font-mono text-zinc-500 hover:text-zinc-950 cursor-pointer">
                Limpiar
            </button>
        </div>

        <!-- Filtros por Andén y Estatus -->
        <div class="flex flex-wrap items-center gap-2 font-mono text-xs">
            <span class="text-[10px] font-bold uppercase text-zinc-500">Andén:</span>
            <template x-for="dk in ['TODOS', 'R1', 'R2', 'R3', 'R4']" :key="dk">
                <button type="button"
                        @click="dockFilter = dk"
                        :class="dockFilter === dk ? 'bg-zinc-950 text-white' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200'"
                        class="px-2.5 py-1 rounded text-[11px] font-bold transition cursor-pointer"
                        x-text="dk"></button>
            </template>

            <span class="text-zinc-300 mx-1">|</span>
            <span class="text-[11px] text-zinc-600" x-text="filteredScheduledList.length + ' registro(s)'"></span>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- 4. TABLA PRINCIPAL OPTIMIZADA (SOLO VISIBLE PARA GERENTE Y EMBARQUES) -->
    <!-- ===================================================================== -->
    <div class="bg-white border border-zinc-200 rounded-lg overflow-hidden shadow-2xs">
        <div class="px-5 py-3.5 border-b border-zinc-200 flex items-center justify-between bg-zinc-50/60">
            <div>
                <h2 class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                    Padrón de Entregas Programadas y Proveedores Activos
                </h2>
                <p class="text-[11px] text-zinc-500">
                    Haz clic en <strong>Ver Ficha</strong> para consultar datos del proveedor, remisión, especificaciones de empaque y etiqueta QR.
                </p>
            </div>
            <a href="{{ route('wms.index') }}"
               class="px-3 py-1.5 rounded border border-zinc-300 bg-white hover:border-zinc-950 text-zinc-900 text-[11px] font-mono font-bold uppercase transition">
                Vista Operador (Acomodo) →
            </a>
        </div>

        <div x-show="filteredScheduledList.length === 0" x-cloak class="py-12 text-center">
            <p class="text-xs font-mono uppercase text-zinc-500">No se encontraron entregas con los criterios seleccionados.</p>
        </div>

        <div x-show="filteredScheduledList.length > 0" class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-zinc-950 text-white font-mono text-[10px] uppercase tracking-wider">
                        <th class="py-3 px-4">Razón Social / Proveedor</th>
                        <th class="py-3 px-3">Folio ASN / Remisión</th>
                        <th class="py-3 px-3">Producto (SKU)</th>
                        <th class="py-3 px-3">Destino CDMX</th>
                        <th class="py-3 px-3">Andén / Cita</th>
                        <th class="py-3 px-3">Volumen Entrega</th>
                        <th class="py-3 px-3">Estatus</th>
                        <th class="py-3 px-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 font-mono tabular-nums">
                    <template x-for="item in filteredScheduledList" :key="item.id">
                        <tr class="even:bg-zinc-50/80 hover:bg-orange-50/40 transition">
                            <!-- Razón Social y RFC -->
                            <td class="py-3 px-4 font-sans">
                                <div class="font-bold text-zinc-950" x-text="item.supplier_name"></div>
                                <div class="text-[11px] font-mono text-zinc-500" x-text="'RFC: ' + (item.supplier_rfc || 'DVE940812KM3')"></div>
                            </td>

                            <!-- Folio ASN / Remisión -->
                            <td class="py-3 px-3">
                                <div class="font-bold text-zinc-950" x-text="item.folio"></div>
                                <div class="text-[10px] text-zinc-500">Remisión verificada</div>
                            </td>

                            <!-- Producto -->
                            <td class="py-3 px-3 font-sans">
                                <div class="flex items-center space-x-1.5">
                                    <span class="font-mono font-extrabold text-zinc-950" x-text="item.sku"></span>
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-mono bg-zinc-100 border border-zinc-300 text-zinc-700"
                                          x-text="item.stow_zone"></span>
                                </div>
                                <div class="text-zinc-700 truncate max-w-xs mt-0.5" x-text="item.product_name"></div>
                            </td>

                            <!-- Destino CDMX -->
                            <td class="py-3 px-3 font-sans">
                                <div class="font-mono font-bold text-zinc-950" x-text="item.branch_code"></div>
                                <div class="text-[11px] text-zinc-600" x-text="item.branch_name"></div>
                            </td>

                            <!-- Andén y Horario -->
                            <td class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded bg-zinc-950 text-white text-[10px] font-bold" x-text="item.dock_code"></span>
                                <div class="text-[11px] text-zinc-600 mt-0.5" x-text="item.eta_window"></div>
                            </td>

                            <!-- Cajas y Peso -->
                            <td class="py-3 px-3">
                                <div class="font-bold text-zinc-950" x-text="item.boxes_expected + ' cajas'"></div>
                                <div class="text-[11px] text-zinc-500" x-text="item.total_weight_kg + ' kg'"></div>
                            </td>

                            <!-- Badge Sobrio de Oficina -->
                            <td class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold border"
                                      :class="item.status === 'COMPLETA'
                                        ? 'bg-zinc-100 border-zinc-300 text-zinc-700'
                                        : 'bg-emerald-50 border-emerald-300 text-emerald-800'"
                                      x-text="item.status || 'PROGRAMADA'"></span>
                            </td>

                            <!-- Acciones Compactas (Ver Ficha, QR, Acomodar) -->
                            <td class="py-3 px-4 text-right whitespace-nowrap space-x-1">
                                <button type="button"
                                        @click="inspectRecord(item)"
                                        class="px-2.5 py-1 rounded border border-zinc-300 bg-white hover:border-zinc-950 text-zinc-800 text-[10px] font-bold uppercase cursor-pointer">
                                    Ver Ficha
                                </button>
                                <a :href="item.qr_label_url"
                                   target="_blank"
                                   class="inline-block px-2.5 py-1 rounded bg-orange-500 hover:bg-orange-600 text-white text-[10px] font-bold uppercase">
                                    QR
                                </a>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- ===================================================================== -->
    <!-- 5. MODAL DE "FICHA DEL PROVEEDOR Y DETALLE DE ENTREGA"                -->
    <!-- ===================================================================== -->
    <div x-show="showNewProductModal" x-cloak
         class="fixed inset-0 z-50 bg-zinc-950/75 flex items-center justify-center p-4">
        <div @click.outside="showNewProductModal = false"
             class="bg-white text-zinc-950 border-2 border-zinc-950 rounded-lg max-w-2xl w-full overflow-hidden shadow-2xl">
            <div class="bg-zinc-950 text-white px-6 py-4 flex items-center justify-between">
                <div>
                    <span class="px-2 py-0.5 rounded bg-orange-500 text-zinc-950 font-mono text-[10px] font-extrabold uppercase">
                        ALTA DE PRODUCTO DE PROVEEDOR · Dokra
                    </span>
                    <h3 class="text-base font-extrabold text-white mt-1">
                        Registrar Nuevo Producto para Almacenar en Sección de Sucursal
                    </h3>
                </div>
                <button type="button" @click="showNewProductModal = false" class="text-zinc-400 hover:text-white cursor-pointer">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <div class="p-6 space-y-4 text-xs">
                <!-- Bloque A: Sucursal Destino (Rack Asignado) y Cantidad de Cajas -->
                <div class="bg-zinc-50 border border-zinc-200 rounded-md p-3.5">
                    <div class="text-[10px] font-mono font-bold uppercase text-orange-600 mb-2">
                        1. Sección de Almacén (Sucursal Destino en CDMX y Cantidad a Enviar)
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                        <div class="sm:col-span-8">
                            <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Sucursal Destino / Rack donde se almacenará</label>
                            <select x-model.number="newProduct.target_branch_id"
                                    class="w-full bg-white border border-zinc-300 rounded px-3 py-2 font-mono font-bold text-zinc-950 focus:outline-none focus:border-orange-500">
                                <template x-for="b in branches" :key="b.id">
                                    <option :value="b.id" x-text="b.code + ' · ' + b.name + ' (Sección ' + b.rack_code + ')'"></option>
                                </template>
                            </select>
                        </div>
                        <div class="sm:col-span-4">
                            <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Cajas Máster a Ingresar</label>
                            <input type="number" min="1" max="500" x-model.number="newProduct.boxes_to_send"
                                   class="w-full bg-white border border-zinc-300 rounded px-3 py-2 font-mono font-extrabold text-zinc-950 focus:outline-none focus:border-orange-500">
                        </div>
                    </div>
                </div>

                <!-- Bloque B: Identificación y Dimensiones Físicas de la Caja Máster -->
                <div class="bg-zinc-50 border border-zinc-200 rounded-md p-3.5 space-y-3">
                    <div class="text-[10px] font-mono font-bold uppercase text-zinc-700">
                        2. Datos Técnicos del Producto y Caja Máster
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Código SKU</label>
                            <input type="text" x-model="newProduct.sku" placeholder="Ej. PAP-012"
                                   class="w-full bg-white border border-zinc-300 rounded px-3 py-1.5 font-mono font-bold uppercase">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Categoría</label>
                            <select x-model="newProduct.category" class="w-full bg-white border border-zinc-300 rounded px-3 py-1.5">
                                <option>Papel y cuadernos</option>
                                <option>Archivo y organización</option>
                                <option>Adhesivos y cintas</option>
                                <option>Escritura</option>
                                <option>Suministros de oficina</option>
                                <option>Corte y medición</option>
                                <option>Arte y escolar</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Presentación Unidad</label>
                            <input type="text" x-model="newProduct.commercial_presentation" placeholder="Ej. 1 paquete (100 h)"
                                   class="w-full bg-white border border-zinc-300 rounded px-3 py-1.5">
                        </div>

                        <div class="sm:col-span-3">
                            <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Nombre / Descripción Comercial</label>
                            <input type="text" x-model="newProduct.name" placeholder="Ej. Papel Bond Ecológico Carta 75g Resma 500 hojas"
                                   class="w-full bg-white border border-zinc-300 rounded px-3 py-1.5">
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Piezas por Caja Máster</label>
                            <input type="number" min="1" x-model.number="newProduct.master_factor"
                                   class="w-full bg-white border border-zinc-300 rounded px-3 py-1.5 font-mono font-bold">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Peso Caja Máster (kg)</label>
                            <input type="number" step="0.1" min="0.5" x-model.number="newProduct.master_weight_kg"
                                   class="w-full bg-white border border-zinc-300 rounded px-3 py-1.5 font-mono font-bold">
                        </div>
                        <div class="grid grid-cols-3 gap-1.5 font-mono">
                            <div>
                                <label class="block text-[9px] font-sans font-bold uppercase text-zinc-600 mb-1">Largo cm</label>
                                <input type="number" min="10" x-model.number="newProduct.master_length_cm" class="w-full bg-white border border-zinc-300 rounded px-2 py-1.5">
                            </div>
                            <div>
                                <label class="block text-[9px] font-sans font-bold uppercase text-zinc-600 mb-1">Ancho cm</label>
                                <input type="number" min="10" x-model.number="newProduct.master_width_cm" class="w-full bg-white border border-zinc-300 rounded px-2 py-1.5">
                            </div>
                            <div>
                                <label class="block text-[9px] font-sans font-bold uppercase text-zinc-600 mb-1">Alto cm</label>
                                <input type="number" min="5" x-model.number="newProduct.master_height_cm" class="w-full bg-white border border-zinc-300 rounded px-2 py-1.5">
                            </div>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-zinc-200 grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" x-model="newProduct.is_liquid" class="rounded">
                            <span>Es líquido / tinta</span>
                        </label>
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" x-model="newProduct.is_hygroscopic" class="rounded">
                            <span>Es papel (higroscópico)</span>
                        </label>
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" x-model="newProduct.is_crushable" class="rounded">
                            <span>Es frágil / deformable</span>
                        </label>
                    </div>
                </div>

                <!-- Dictamen Automático de Estiba Calculado en Vivo -->
                <div class="p-3.5 rounded-md bg-zinc-950 text-white font-mono text-xs flex flex-wrap items-center justify-between gap-2">
                    <span>Sección Destino: <strong class="text-orange-400" x-text="(getBranch(newProduct.target_branch_id)?.code || 'SUC-01') + ' (' + (getBranch(newProduct.target_branch_id)?.rack_code || 'RACK-01') + ')'"></strong></span>
                    <span>Densidad: <strong class="text-orange-400" x-text="previewNewProductStats.density + ' kg/m³'"></strong></span>
                    <span>Piso en Tarima: <strong class="text-orange-400" x-text="previewNewProductStats.zone"></strong></span>
                </div>
            </div>

            <div class="px-6 py-3 bg-zinc-100 border-t border-zinc-200 flex justify-end space-x-2">
                <button type="button" @click="showNewProductModal = false"
                        class="px-4 py-1.5 rounded border border-zinc-300 bg-white text-xs font-bold uppercase cursor-pointer">
                    Cancelar
                </button>
                <button type="button"
                        @click="registerSupplierProduct()"
                        :disabled="submittingProduct"
                        class="px-5 py-1.5 rounded bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold uppercase cursor-pointer">
                    <span x-show="!submittingProduct">Guardar Producto y Asignar a Sucursal Destino</span>
                    <span x-show="submittingProduct" x-cloak>Guardando...</span>
                </button>
            </div>
        </div>
    </div>
    <div x-show="selectedRecord !== null" x-cloak
         class="fixed inset-0 z-50 bg-zinc-950/75 flex items-center justify-center p-4">
        <template x-if="selectedRecord">
            <div @click.outside="selectedRecord = null"
                 class="bg-white text-zinc-950 border-2 border-zinc-950 rounded-lg max-w-2xl w-full overflow-hidden shadow-2xl">
                <!-- Cabecera del Modal -->
                <div class="bg-zinc-950 text-white px-6 py-4 flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center space-x-2">
                            <span class="px-2 py-0.5 rounded bg-orange-500 text-zinc-950 font-mono text-[10px] font-extrabold uppercase">
                                FICHA DE PROVEEDOR Y ENTREGA
                            </span>
                            <span class="font-mono text-xs text-zinc-400" x-text="'FOLIO: ' + selectedRecord.folio"></span>
                        </div>
                        <h3 class="text-lg font-extrabold text-white mt-1" x-text="selectedRecord.supplier_name"></h3>
                    </div>
                    <button type="button" @click="selectedRecord = null" class="text-zinc-400 hover:text-white cursor-pointer">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <!-- Cuerpo del Modal en 3 Bloques Administrativos -->
                <div class="p-6 space-y-5 text-xs">
                    <!-- Bloque A: Datos de Identificación y Contacto del Proveedor -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-zinc-50 border border-zinc-200 rounded-md p-3.5">
                        <div>
                            <span class="text-[10px] font-bold uppercase text-zinc-500 block">ID Proveedor (RFC)</span>
                            <strong class="font-mono text-zinc-950" x-text="selectedRecord.supplier_rfc || 'DVE940812KM3'"></strong>
                            <span class="block text-[11px] text-emerald-700 font-semibold mt-0.5">● Estatus: Activo</span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase text-zinc-500 block">Ejecutivo de Cuenta / Contacto</span>
                            <strong class="text-zinc-950" x-text="selectedRecord.contact_name || 'Lic. Adriana Morales Vega'"></strong>
                            <span class="block text-[11px] font-mono text-zinc-600" x-text="selectedRecord.contact_phone || '55 5340 8910'"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase text-zinc-500 block">Correo Electrónico</span>
                            <strong class="font-mono text-[11px] text-zinc-900 break-all" x-text="selectedRecord.contact_email || 'logistica@proveedorcdmx.mx'"></strong>
                        </div>
                    </div>

                    <!-- Bloque B: Especificaciones Logísticas de la Entrega -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 font-mono">
                        <div class="p-3 rounded border border-zinc-200">
                            <span class="text-[10px] text-zinc-500 uppercase block">Destino CDMX</span>
                            <strong class="text-sm text-zinc-950" x-text="selectedRecord.branch_code"></strong>
                            <span class="block text-[11px] font-sans text-zinc-600" x-text="selectedRecord.branch_name + ' (' + selectedRecord.rack_code + ')'"></span>
                        </div>
                        <div class="p-3 rounded border border-zinc-200">
                            <span class="text-[10px] text-zinc-500 uppercase block">Andén / Ventana</span>
                            <strong class="text-sm text-zinc-950" x-text="'Andén ' + selectedRecord.dock_code"></strong>
                            <span class="block text-[11px] text-zinc-600" x-text="selectedRecord.eta_window"></span>
                        </div>
                        <div class="p-3 rounded border border-zinc-200">
                            <span class="text-[10px] text-zinc-500 uppercase block">Cajas / Unidades</span>
                            <strong class="text-sm text-zinc-950" x-text="selectedRecord.boxes_expected + ' cajas'"></strong>
                            <span class="block text-[11px] text-zinc-600" x-text="Number(selectedRecord.total_pieces).toLocaleString('es-MX') + ' piezas'"></span>
                        </div>
                        <div class="p-3 rounded border border-zinc-200">
                            <span class="text-[10px] text-zinc-500 uppercase block">Peso Total Lote</span>
                            <strong class="text-sm text-zinc-950" x-text="selectedRecord.total_weight_kg + ' kg'"></strong>
                            <span class="block text-[11px] text-zinc-600" x-text="selectedRecord.stow_zone"></span>
                        </div>
                    </div>

                    <!-- Bloque C: Ficha Técnica del Producto y Etiqueta QR -->
                    <div class="border border-zinc-300 rounded-md p-4 flex flex-col sm:flex-row items-center justify-between gap-4 bg-zinc-50">
                        <div class="space-y-1">
                            <div class="text-[10px] font-mono font-bold uppercase text-orange-600">PRODUCTO REGISTRADO EN ENTREGA</div>
                            <div class="text-sm font-extrabold text-zinc-950" x-text="selectedRecord.sku + ' · ' + selectedRecord.product_name"></div>
                            <div class="text-[11px] font-mono text-zinc-600">
                                Zona de Estiba: <strong x-text="selectedRecord.stow_zone"></strong> ·
                                Factor: <strong x-text="'×' + (selectedRecord.master_factor || 10)"></strong>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 shrink-0">
                            <a :href="selectedRecord.qr_label_url" target="_blank"
                               class="px-3.5 py-2 rounded bg-orange-500 hover:bg-orange-600 text-white text-xs font-mono font-bold uppercase">
                                <i class="fa-solid fa-qrcode mr-1"></i> Imprimir Etiquetas QR
                            </a>
                            <a :href="selectedRecord.acomodo_url"
                               class="px-3.5 py-2 rounded bg-zinc-950 hover:bg-zinc-800 text-white text-xs font-mono font-bold uppercase">
                                Ir a Acomodo →
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Pie del Modal -->
                <div class="px-6 py-3 bg-zinc-100 border-t border-zinc-200 flex justify-end">
                    <button type="button" @click="selectedRecord = null"
                            class="px-4 py-1.5 rounded border border-zinc-300 bg-white hover:bg-zinc-50 text-xs font-bold uppercase text-zinc-700 cursor-pointer">
                        Cerrar Ficha
                    </button>
                </div>
            </div>
        </template>
    </div>

</div>
@endsection

@section('extra-scripts')
<script>
function supplierPortalApp(config) {
    const defaultProd1 = config.products.find(p => p.sku === 'PAP-007') || config.products[0];
    const defaultProd2 = config.products.find(p => p.sku === 'OFI-006') || config.products[0];
    const defaultBranch1 = config.branches.find(b => b.code === 'SUC-02') || config.branches[0];
    const defaultBranch2 = config.branches.find(b => b.code === 'SUC-04') || config.branches[0];

    return {
        csrfToken: config.csrfToken,
        products: config.products,
        branches: config.branches,
        scheduleUrl: config.scheduleUrl,
        productsStoreUrl: config.productsStoreUrl,
        qrLabelBaseUrl: config.qrLabelBaseUrl,
        scheduledList: config.initialArrivals || [],
        createdArrivals: [],
        batchQrLabelUrl: '',

        // Visibilidad de paneles y filtros de tabla
        showFormPanel: true,
        showNewProductModal: false,
        submittingProduct: false,
        tableSearch: '',
        dockFilter: 'TODOS',
        selectedRecord: null,

        newProduct: {
            sku: 'PRO-' + Math.floor(100 + Math.random() * 899),
            name: '',
            category: 'Papel y cuadernos',
            commercial_presentation: '1 paquete',
            master_factor: 12,
            master_weight_kg: 14.0,
            master_length_cm: 44,
            master_width_cm: 30,
            master_height_cm: 22,
            is_liquid: false,
            is_hygroscopic: true,
            is_crushable: false,
            target_branch_id: defaultBranch1?.id || 1,
            boxes_to_send: 10
        },

        // Campos del formulario de Aviso de Entrega (ASN) y Remisión
        asnFolio: 'ASN-2026-0105',
        remissionFolio: 'REM-DM-99104',
        supplierName: 'Distribuidora Metropolitana S.A.',
        contactName: 'Lic. Adriana Morales Vega',
        contactPhone: '55 5340 8910 · logistica@dmetropolitana.mx',
        existingFolios: ['REM-AG-77120', 'REM-DV-98412', 'REM-OL-44019', 'REM-AN-30218'],
        selectedDate: '2026-11-26',
        selectedDock: 'R2',
        startHour: 14,
        durationHours: 2,
        submitting: false,
        successMessage: '',
        errorMessage: '',

        occupiedSlots: {
            'R1': [8, 9, 10],
            'R2': [10, 11],
            'R3': [12, 13],
            'R4': []
        },

        packages: [
            { product_id: defaultProd1?.id, branch_id: defaultBranch1?.id, boxes: 12 },
            { product_id: defaultProd2?.id, branch_id: defaultBranch2?.id, boxes: 20 }
        ],

        get filteredScheduledList() {
            const q = this.tableSearch.trim().toLowerCase();
            return this.scheduledList.filter(item => {
                const matchDock = this.dockFilter === 'TODOS' || item.dock_code === this.dockFilter;
                const matchText = !q ||
                    (item.supplier_name || '').toLowerCase().includes(q) ||
                    (item.folio || '').toLowerCase().includes(q) ||
                    (item.sku || '').toLowerCase().includes(q) ||
                    (item.product_name || '').toLowerCase().includes(q) ||
                    (item.branch_code || '').toLowerCase().includes(q) ||
                    (item.branch_name || '').toLowerCase().includes(q);
                return matchDock && matchText;
            });
        },

        get totalScheduledBoxes() {
            return this.scheduledList.reduce((acc, item) => acc + Number(item.boxes_expected || 0), 0);
        },

        get totalScheduledWeight() {
            return this.scheduledList.reduce((acc, item) => acc + Number(item.total_weight_kg || 0), 0).toFixed(1);
        },

        get isDuplicateFolio() {
            return this.existingFolios.includes(this.remissionFolio.trim().toUpperCase());
        },

        get isFolioValid() {
            return this.remissionFolio.trim().length >= 4 && !this.isDuplicateFolio && this.asnFolio.trim().length >= 3;
        },

        get totalBoxesCount() {
            return this.packages.reduce((acc, p) => acc + Number(p.boxes || 0), 0);
        },

        get totalWeightKg() {
            return this.packages.reduce((acc, p) => {
                const prod = this.getProduct(p.product_id);
                return acc + (Number(p.boxes || 0) * Number(prod?.master_weight_kg || 0));
            }, 0).toFixed(1);
        },

        getProduct(id) {
            return this.products.find(p => Number(p.id) === Number(id)) || this.products[0];
        },

        getBranch(id) {
            return this.branches.find(b => Number(b.id) === Number(id)) || this.branches[0];
        },

        previewQrUrl(pkg) {
            return this.qrLabelBaseUrl + '?branch_id=' + pkg.branch_id + '&product_id=' + pkg.product_id + '&boxes=' + pkg.boxes;
        },

        get previewNewProductStats() {
            const l = Math.max(10, Number(this.newProduct.master_length_cm || 30));
            const w = Math.max(10, Number(this.newProduct.master_width_cm || 25));
            const h = Math.max(5, Number(this.newProduct.master_height_cm || 20));
            const kg = Math.max(0.5, Number(this.newProduct.master_weight_kg || 5));
            const vol = Math.max(0.005, (l * w * h) / 1000000);
            const dens = Math.round(kg / vol);
            let zone = '2 · Intermedio / Estándar';
            if (this.newProduct.is_crushable || dens < 240) {
                zone = '3 · Superior / Ligero';
            } else if (kg >= 12.5 || dens >= 600) {
                zone = '1 · Base / Pesado';
            }
            return { volume: vol.toFixed(4), density: dens, zone };
        },

        openNewProductModal() {
            this.newProduct.sku = 'PRO-' + String(this.products.length + 1).padStart(3, '0');
            this.newProduct.target_branch_id = this.packages[0]?.branch_id || this.branches[0]?.id || 1;
            this.showNewProductModal = true;
        },

        async registerSupplierProduct() {
            this.submittingProduct = true;
            this.errorMessage = '';
            try {
                const res = await fetch(this.productsStoreUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken
                    },
                    body: JSON.stringify({
                        sku: this.newProduct.sku,
                        name: this.newProduct.name,
                        category: this.newProduct.category,
                        commercial_presentation: this.newProduct.commercial_presentation,
                        master_factor: Number(this.newProduct.master_factor || 12),
                        master_weight_kg: Number(this.newProduct.master_weight_kg || 10),
                        master_length_cm: Number(this.newProduct.master_length_cm || 40),
                        master_width_cm: Number(this.newProduct.master_width_cm || 30),
                        master_height_cm: Number(this.newProduct.master_height_cm || 20),
                        is_liquid: Boolean(this.newProduct.is_liquid),
                        is_hygroscopic: Boolean(this.newProduct.is_hygroscopic),
                        is_crushable: Boolean(this.newProduct.is_crushable)
                    })
                });

                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data.message || 'Verifica los datos del nuevo producto.');
                }

                this.products = data.products;
                const branch = this.getBranch(this.newProduct.target_branch_id);

                // Agregar directamente la partida con el nuevo producto y la sucursal destino elegida
                this.packages.unshift({
                    product_id: data.product.id,
                    branch_id: Number(this.newProduct.target_branch_id),
                    boxes: Math.max(1, Number(this.newProduct.boxes_to_send || 10))
                });

                this.showNewProductModal = false;
                this.showFormPanel = true;
                this.newProduct.name = '';
                this.successMessage = 'Producto ' + data.product.sku + ' (' + data.product.name + ') dado de alta en zona "' + data.product.stow_zone + '" y asignado a la sección ' + (branch?.code || '') + ' (' + (branch?.rack_code || '') + '). Haz clic en "Guardar Entrega y Generar Etiquetas QR" para confirmar su envío.';
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.submittingProduct = false;
            }
        },

        inspectRecord(item) {
            this.selectedRecord = item;
        },

        openSupplierCard() {
            if (this.scheduledList.length > 0) {
                this.selectedRecord = this.scheduledList[0];
                return;
            }
            const prod = this.getProduct(this.packages[0]?.product_id);
            const branch = this.getBranch(this.packages[0]?.branch_id);
            this.selectedRecord = {
                folio: this.asnFolio + ' · ' + this.remissionFolio,
                supplier_name: this.supplierName,
                supplier_rfc: config.defaultSupplierRfc,
                contact_name: this.contactName,
                contact_phone: '55 5340 8910',
                contact_email: 'logistica@dmetropolitana.mx',
                branch_code: branch?.code || 'SUC-02',
                branch_name: branch?.name || 'Polanco',
                rack_code: branch?.rack_code || 'RACK-02',
                dock_code: this.selectedDock,
                eta_window: this.selectedDate + ' · ' + this.startHour + ':00',
                sku: prod?.sku || 'PAP-007',
                product_name: prod?.name || 'Papel Bond',
                stow_zone: prod?.stow_zone || 'Base / Pesado',
                master_factor: prod?.master_factor || 10,
                boxes_expected: this.packages[0]?.boxes || 12,
                total_pieces: (this.packages[0]?.boxes || 12) * (prod?.master_factor || 10),
                total_weight_kg: ((this.packages[0]?.boxes || 12) * (prod?.master_weight_kg || 23.7)).toFixed(1),
                qr_label_url: this.previewQrUrl(this.packages[0]),
                acomodo_url: '/acomodo'
            };
        },

        isOccupied(dock, hour) {
            return (this.occupiedSlots[dock] || []).includes(hour);
        },

        isInMyRange(dock, hour) {
            return this.selectedDock === dock && hour >= Number(this.startHour) && hour < (Number(this.startHour) + Number(this.durationHours));
        },

        get hasScheduleConflict() {
            const start = Number(this.startHour);
            const end = start + Number(this.durationHours);
            if (end > 18 || start < 8 || Number(this.durationHours) < 1) return true;
            for (let h = start; h < end; h++) {
                if (this.isOccupied(this.selectedDock, h)) return true;
            }
            return false;
        },

        addPackage() {
            const nextProd = this.products[(this.packages.length * 5) % this.products.length] || this.products[0];
            const nextBranch = this.branches[this.packages.length % this.branches.length] || this.branches[0];
            this.packages.push({
                product_id: nextProd.id,
                branch_id: nextBranch.id,
                boxes: 10
            });
        },

        removePackage(idx) {
            if (this.packages.length > 1) this.packages.splice(idx, 1);
        },

        async confirmSchedule() {
            if (!this.isFolioValid || this.hasScheduleConflict || this.packages.length === 0) return;
            this.submitting = true;
            this.successMessage = '';
            this.errorMessage = '';

            try {
                const res = await fetch(this.scheduleUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken
                    },
                    body: JSON.stringify({
                        asn_folio: this.asnFolio,
                        remission_folio: this.remissionFolio,
                        supplier_name: this.supplierName,
                        selected_date: this.selectedDate,
                        dock_code: this.selectedDock,
                        start_hour: Number(this.startHour),
                        duration_hours: Number(this.durationHours),
                        packages: this.packages.map(p => ({
                            product_id: Number(p.product_id),
                            branch_id: Number(p.branch_id),
                            boxes: Number(p.boxes || 1)
                        }))
                    })
                });

                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data.message || 'No se pudo registrar la entrega.');
                }

                const start = Number(this.startHour);
                const end = start + Number(this.durationHours);
                for (let h = start; h < end; h++) {
                    this.occupiedSlots[this.selectedDock].push(h);
                }

                this.createdArrivals = data.created_arrivals || [];
                this.batchQrLabelUrl = data.batch_qr_label_url || '';
                this.scheduledList = [...this.createdArrivals, ...this.scheduledList];
                this.successMessage = data.message;
                this.selectedDock = 'R4';
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.submitting = false;
            }
        }
    };
}
</script>
@endsection
