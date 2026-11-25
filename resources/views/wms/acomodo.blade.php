@extends('layouts.app')

@section('title', 'ERP de Almacén · Acomodo, Racks y Salidas — CEDIS CDMX')
@section('module-code', '03 · OPERADOR CEDIS · ERP DE ACOMODO, INVENTARIO Y ÓRDENES DE SALIDA')

@section('extra-head')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
@endsection

@section('content')
<div x-data="wmsSlottingApp({
        csrfToken: @js(csrf_token()),
        branchesSummary: @js($branchesSummary),
        products: @js($products),
        initialState: @js($initialState),
        initialRecommendation: @js($initialRecommendation ?? null),
        routes: {
            stateBase: '/acomodo/sucursal',
            recommend: @js(route('wms.recommend', [], false)),
            receive: @js(route('wms.receive', [], false)),
            receiveBatch: @js(route('wms.receive-batch', [], false)),
            slotBase: '/acomodo/tarimas',
            arrivalsStore: @js(route('wms.arrivals.store', [], false)),
            outboundStore: @js(route('wms.outbound.store', [], false)),
            qrLabel: @js(route('wms.qr.label', [], false)),
            qrScan: @js(route('wms.qr.scan', [], false)),
            productsStore: @js(route('wms.products.store', [], false)),
            resetDemo: @js(route('wms.reset-demo', [], false)),
        }
     })"
     x-init="init()"
     class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

    <!-- ===================================================================== -->
    <!-- 1. BARRA SUPERIOR ADMINISTRATIVA (ENCABEZADO, KPIS Y ACCIONES)        -->
    <!-- ===================================================================== -->
    <div class="bg-white border border-zinc-200 rounded-lg p-5 shadow-2xs">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-4 border-b border-zinc-200">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-2 py-0.5 text-[10px] font-mono font-bold uppercase tracking-widest bg-zinc-950 text-white rounded">
                        ERP ALMACÉN · CEDIS CDMX
                    </span>
                    <a href="{{ route('dashboard.recepcion') }}"
                       class="px-2 py-0.5 text-[10px] font-mono font-semibold bg-zinc-100 hover:bg-zinc-200 border border-zinc-300 text-zinc-700 rounded transition">
                        Cambiar a Vista Handheld QR →
                    </a>
                    <span class="px-2 py-0.5 text-[10px] font-mono font-bold bg-emerald-50 border border-emerald-300 text-emerald-800 rounded">
                        ● NOM-036 ACTIVA
                    </span>
                </div>
                <h1 class="text-xl sm:text-2xl font-extrabold text-zinc-950 tracking-tight mt-1">
                    Control de Acomodo, Inventario por Rack y Órdenes de Salida
                </h1>
                <p class="text-xs text-zinc-500 mt-0.5">
                    Administración centralizada de entradas, asignación de ubicación en tarima y rack (4×8), planeación de llegadas posteriores y despacho Tortón (&ge;90%).
                </p>
            </div>

            <!-- Botones Principales de Oficina -->
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <button type="button"
                        @click="showEntryForm = !showEntryForm; tab = 'llegadas'"
                        class="px-3.5 py-2 rounded-md bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold uppercase tracking-wider transition cursor-pointer flex items-center space-x-1.5">
                    <i class="fa-solid" :class="showEntryForm ? 'fa-chevron-up' : 'fa-plus'"></i>
                    <span x-text="showEntryForm ? 'Ocultar Captura de Entrada' : 'Registrar Entrada de Mercancía'"></span>
                </button>

                <button type="button"
                        @click="showNewProductModal = true"
                        class="px-3 py-2 rounded-md bg-zinc-950 hover:bg-zinc-800 text-white text-xs font-bold uppercase tracking-wider transition cursor-pointer flex items-center space-x-1.5">
                    <i class="fa-solid fa-box-open text-orange-400"></i>
                    <span>Alta de Producto</span>
                </button>

                <button type="button"
                        @click="showQrScannerModal = true"
                        class="px-3 py-2 rounded-md border border-zinc-300 bg-white hover:border-zinc-950 text-zinc-800 text-xs font-bold uppercase tracking-wider transition cursor-pointer flex items-center space-x-1.5">
                    <i class="fa-solid fa-qrcode text-orange-500"></i>
                    <span>Lector QR</span>
                </button>

                <button type="button"
                        @click="resetDemo()"
                        :disabled="busy"
                        title="Restaurar datos iniciales"
                        class="px-2.5 py-2 rounded-md border border-zinc-300 bg-white hover:border-zinc-950 text-zinc-600 hover:text-zinc-950 text-xs font-mono font-bold transition cursor-pointer">
                    <i class="fa-solid fa-rotate-left"></i>
                </button>
            </div>
        </div>

        <!-- Selector Administrativo de Destino + 4 KPIs Operativos -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 pt-4 font-mono tabular-nums text-xs">
            <div class="p-3 rounded-md bg-zinc-950 text-white flex flex-col justify-between">
                <label class="text-[10px] text-orange-400 font-bold uppercase block">Sucursal Destino / Rack Activo</label>
                <select x-model="selectedBranchId"
                        @change="loadBranchState(selectedBranchId)"
                        class="mt-1 w-full bg-zinc-900 border border-zinc-700 rounded px-2 py-1 text-xs font-bold text-white focus:outline-none focus:border-orange-500 cursor-pointer">
                    <template x-for="b in branchesSummary" :key="b.id">
                        <option :value="b.id"
                                x-text="b.code + ' · ' + b.name + ' (' + b.slotted_count + '/32' + (b.ready_for_torton ? ' · ★' : '') + ')'">
                        </option>
                    </template>
                </select>
            </div>

            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block" x-text="'Ocupación ' + state.branch.rack_code"></span>
                    <strong class="text-base font-extrabold text-zinc-950" x-text="state.slotted_count + ' / 32 pos'"></strong>
                    <span class="block text-[10px] font-sans text-zinc-500" x-text="state.open_pallets.length + ' tarima(s) en piso'"></span>
                </div>
                <i class="fa-solid fa-table-cells text-zinc-400 text-base"></i>
            </div>

            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block">Llegadas Programadas</span>
                    <strong class="text-base font-extrabold text-zinc-950" x-text="state.planned_arrivals.length + ' partidas'"></strong>
                    <span class="block text-[10px] font-sans text-zinc-500"
                          x-text="(state.lookahead.pending_base_boxes + state.lookahead.pending_inter_boxes + state.lookahead.pending_light_boxes) + ' cajas por arribar'"></span>
                </div>
                <i class="fa-solid fa-truck-Clock text-zinc-400 text-base"></i>
            </div>

            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block">Consolidación Tortón</span>
                    <strong class="text-base font-extrabold"
                            :class="state.dispatch_plan.ready_for_torton ? 'text-emerald-700' : 'text-zinc-950'"
                            x-text="state.dispatch_plan.weight_pct + '% P · ' + state.dispatch_plan.volume_pct + '% V'"></strong>
                    <span class="block text-[10px] font-sans"
                          :class="state.dispatch_plan.ready_for_torton ? 'text-emerald-700 font-bold' : 'text-zinc-500'"
                          x-text="state.dispatch_plan.ready_for_torton ? '● LIBERACIÓN ≥90% OK' : 'En acumulación (<90%)'"></span>
                </div>
                <i class="fa-solid fa-truck-Moving text-orange-500 text-base"></i>
            </div>

            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block">Cuota NOM-036 Turno</span>
                    <strong class="text-base font-extrabold text-zinc-950"
                            x-text="Number(state.operator_log.moved_weight_kg).toLocaleString('es-MX') + ' kg'"></strong>
                    <span class="block text-[10px] font-sans text-zinc-500"
                          x-text="state.operator_log.usage_pct + '% de ' + Number(state.operator_log.daily_limit_kg).toLocaleString('es-MX') + ' kg'"></span>
                </div>
                <i class="fa-solid fa-shield-heart text-zinc-400 text-base"></i>
            </div>
        </div>

        <!-- Barra de Navegación Interna (4 Secciones Administrativas) -->
        <nav class="flex space-x-6 overflow-x-auto mt-4 pt-2 border-t border-zinc-200 -mb-5 text-xs font-bold uppercase tracking-wider">
            <button type="button" @click="tab = 'llegadas'"
                    :class="tab === 'llegadas' ? 'border-orange-500 text-zinc-950' : 'border-transparent text-zinc-500 hover:text-zinc-900'"
                    class="py-3 border-b-2 whitespace-nowrap transition cursor-pointer flex items-center space-x-2">
                <span class="font-mono text-[11px] text-orange-500">01</span>
                <span>Entradas, Tarimas y Cola Programada</span>
            </button>

            <button type="button" @click="tab = 'rack'"
                    :class="tab === 'rack' ? 'border-orange-500 text-zinc-950' : 'border-transparent text-zinc-500 hover:text-zinc-900'"
                    class="py-3 border-b-2 whitespace-nowrap transition cursor-pointer flex items-center space-x-2">
                <span class="font-mono text-[11px] text-orange-500">02</span>
                <span>Inventario y Mapa de Rack 4×8 (<span x-text="state.branch.rack_code"></span>)</span>
            </button>

            <button type="button" @click="tab = 'salidas'"
                    :class="tab === 'salidas' ? 'border-orange-500 text-zinc-950' : 'border-transparent text-zinc-500 hover:text-zinc-900'"
                    class="py-3 border-b-2 whitespace-nowrap transition cursor-pointer flex items-center space-x-2">
                <span class="font-mono text-[11px] text-orange-500">03</span>
                <span>Órdenes de Salida (Tortón 12 Tarimas)</span>
            </button>

            <button type="button" @click="tab = 'catalogo'"
                    :class="tab === 'catalogo' ? 'border-orange-500 text-zinc-950' : 'border-transparent text-zinc-500 hover:text-zinc-900'"
                    class="py-3 border-b-2 whitespace-nowrap transition cursor-pointer flex items-center space-x-2">
                <span class="font-mono text-[11px] text-orange-500">04</span>
                <span>Catálogo Maestro (<span x-text="products.length"></span> SKUs)</span>
            </button>
        </nav>
    </div>

    <!-- Alertas Globales -->
    <div x-show="toastMessage !== ''" x-cloak
         class="p-3.5 rounded-md bg-zinc-950 text-white border-l-4 border-orange-500 text-xs flex items-center justify-between gap-3 shadow-2xs">
        <div class="flex items-center space-x-2">
            <i class="fa-solid fa-circle-check text-orange-500"></i>
            <span class="font-medium" x-text="toastMessage"></span>
        </div>
        <button type="button" @click="toastMessage = ''" class="text-zinc-400 hover:text-white cursor-pointer">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <div x-show="errorMessage !== ''" x-cloak
         class="p-3.5 rounded-md bg-red-950 text-white border-l-4 border-red-500 text-xs flex items-center justify-between gap-3 shadow-2xs">
        <div class="flex items-center space-x-2">
            <i class="fa-solid fa-triangle-exclamation text-red-400"></i>
            <span class="font-medium" x-text="errorMessage"></span>
        </div>
        <button type="button" @click="errorMessage = ''" class="text-red-300 hover:text-white cursor-pointer">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- ===================================================================== -->
    <!-- SECCIÓN 01: ENTRADAS, TARIMAS EN PISO Y COLA DE LLEGADAS POSTERIORES  -->
    <!-- ===================================================================== -->
    <div x-show="tab === 'llegadas'" class="space-y-5">

        <!-- FORMULARIO ADMINISTRATIVO EN CUADRÍCULA DE 3 BLOQUES -->
        <div x-show="showEntryForm" class="bg-white border-2 border-zinc-950 rounded-lg overflow-hidden shadow-2xs">
            <div class="bg-zinc-950 text-white px-5 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div class="flex items-center space-x-2">
                    <span class="w-5 h-5 rounded bg-orange-500 text-zinc-950 font-mono text-xs font-extrabold flex items-center justify-center">
                        <i class="fa-solid fa-boxes-packing"></i>
                    </span>
                    <h2 class="text-xs font-extrabold uppercase tracking-wider">
                        Captura de Entrada de Producto y Dictamen Automático de Ubicación
                    </h2>
                </div>
                <span class="font-mono text-[11px] text-orange-400"
                      x-text="'Destino Activo: ' + state.branch.code + ' · ' + state.branch.name + ' (' + state.branch.rack_code + ')'"></span>
            </div>

            <div class="p-5 space-y-5">
                <!-- Cuadrícula Superior: 2 Bloques (Captura de Producto | Dictamen de Ubicación) -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

                    <!-- Bloque 1 (6/12): Datos del Producto a Ingresar -->
                    <div class="lg:col-span-6 bg-zinc-50 border border-zinc-200 rounded-md p-4 space-y-3">
                        <div class="flex items-center justify-between border-b border-zinc-200 pb-2">
                            <span class="text-[11px] font-extrabold uppercase tracking-wider text-zinc-900">
                                1. Selección de Producto y Cantidad
                            </span>
                            <button type="button" @click="showNewProductModal = true"
                                    class="text-[11px] font-bold text-orange-600 hover:text-orange-700 cursor-pointer">
                                + Nuevo SKU
                            </button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Filtrar por Categoría</label>
                                <select x-model="selectedCategory" @change="onCategoryFilterChange()"
                                        class="w-full bg-white border border-zinc-300 rounded px-2.5 py-2 text-xs text-zinc-950 focus:outline-none focus:border-orange-500">
                                    <template x-for="cat in categoriesList" :key="cat.key">
                                        <option :value="cat.key" x-text="cat.label + ' (' + cat.count + ')'"></option>
                                    </template>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Buscar por SKU o Nombre</label>
                                <input type="text"
                                       x-model="receiveSearch"
                                       placeholder="Ej. PAP-007, Grapas, Resistol..."
                                       class="w-full bg-white border border-zinc-300 rounded px-2.5 py-2 text-xs text-zinc-950 focus:outline-none focus:border-orange-500">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Producto Seleccionado (Catálogo Auditado)</label>
                                <select x-model.number="selectedProductId"
                                        @change="onBoxQtyChanged()"
                                        class="w-full bg-white border border-zinc-300 rounded px-3 py-2 text-xs font-mono font-bold text-zinc-950 focus:outline-none focus:border-orange-500">
                                    <template x-for="p in filteredReceiveProducts" :key="p.id">
                                        <option :value="p.id"
                                                x-text="p.sku + ' · ' + p.name + ' [' + p.master_weight_kg + ' kg · ' + p.stow_zone + ']'">
                                        </option>
                                    </template>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Cantidad de Cajas Máster</label>
                                <div class="flex items-center space-x-1.5">
                                    <button type="button" @click="setBoxQty(Math.max(1, Number(receiveBoxes) - 1))"
                                            class="w-8 h-8 rounded bg-white border border-zinc-300 hover:border-zinc-950 font-mono font-bold cursor-pointer">−</button>
                                    <input type="number" min="1" max="500"
                                           x-model.number="receiveBoxes"
                                           @input.debounce.200ms="onBoxQtyChanged()"
                                           class="w-20 text-center bg-white border border-zinc-300 rounded py-1.5 font-mono font-extrabold text-zinc-950 focus:outline-none focus:border-orange-500">
                                    <button type="button" @click="setBoxQty(Math.min(500, Number(receiveBoxes) + 1))"
                                            class="w-8 h-8 rounded bg-white border border-zinc-300 hover:border-zinc-950 font-mono font-bold cursor-pointer">+</button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Distancia Acarreo (NOM-036)</label>
                                <select x-model="distanceBand" @change="fetchRecommendation()"
                                        class="w-full bg-white border border-zinc-300 rounded px-2.5 py-2 font-mono text-xs text-zinc-950 focus:outline-none focus:border-orange-500">
                                    <option value="under_10m">&lt; 10 m (Tope 10,000 kg)</option>
                                    <option value="10_to_20m">10–20 m (Tope 6,000 kg)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Resumen Físico del Lote -->
                        <template x-if="activeProduct">
                            <div class="pt-2 border-t border-zinc-200 grid grid-cols-3 gap-2 font-mono text-[11px] text-center">
                                <div class="bg-white border border-zinc-200 rounded p-2">
                                    <span class="text-[9px] text-zinc-500 block">PIEZAS TOTALES</span>
                                    <strong class="text-zinc-950" x-text="(Number(receiveBoxes) * Number(activeProduct.master_factor)).toLocaleString('es-MX') + ' uds'"></strong>
                                </div>
                                <div class="bg-white border border-zinc-200 rounded p-2">
                                    <span class="text-[9px] text-zinc-500 block">PESO BRUTO</span>
                                    <strong class="text-zinc-950" x-text="(Number(receiveBoxes) * Number(activeProduct.master_weight_kg)).toFixed(1) + ' kg'"></strong>
                                </div>
                                <div class="bg-white border border-zinc-200 rounded p-2">
                                    <span class="text-[9px] text-zinc-500 block">DENSIDAD / ECT</span>
                                    <strong class="text-zinc-950" x-text="activeProduct.master_density_kg_m3 + ' kg/m³ · ' + activeProduct.ect_rating + ' ECT'"></strong>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Bloque 2 (6/12): Dictamen de Ubicación (Tarima, Piso, Coordenada de Rack y Normativa) -->
                    <div class="lg:col-span-6 bg-zinc-50 border border-zinc-200 rounded-md p-4 flex flex-col justify-between space-y-3">
                        <div>
                            <div class="flex items-center justify-between border-b border-zinc-200 pb-2 mb-3">
                                <span class="text-[11px] font-extrabold uppercase tracking-wider text-zinc-900">
                                    2. Dictamen de Ubicación Asignada (¿Dónde se pone?)
                                </span>
                                <template x-if="recommendation">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold"
                                          :class="zoneBadgeClass(recommendation.product.zone_priority)"
                                          x-text="'PISO ' + recommendation.product.zone_priority + ' · ' + recommendation.product.stow_zone"></span>
                                </template>
                            </div>

                            <template x-if="recommendation">
                                <div class="space-y-3">
                                    <!-- Tabla Compacta de Asignación de Tarima y Rack -->
                                    <div class="bg-white border border-zinc-300 rounded overflow-hidden">
                                        <table class="w-full text-left text-xs border-collapse font-mono tabular-nums">
                                            <thead>
                                                <tr class="bg-zinc-100 border-b border-zinc-300 text-[10px] uppercase text-zinc-700">
                                                    <th class="py-1.5 px-2.5">Tarima Destino</th>
                                                    <th class="py-1.5 px-2.5">Cajas · Cama</th>
                                                    <th class="py-1.5 px-2.5">Coordenada Rack</th>
                                                    <th class="py-1.5 px-2.5">Ocupación Tarima</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-zinc-200">
                                                <template x-for="(alloc, idx) in recommendation.allocations" :key="idx">
                                                    <tr>
                                                        <td class="py-2 px-2.5">
                                                            <strong class="text-zinc-950" x-text="alloc.pallet_code"></strong>
                                                            <span class="block text-[9px] text-zinc-500" x-text="alloc.is_new_pallet ? 'Nueva tarima' : 'Tarima abierta'"></span>
                                                        </td>
                                                        <td class="py-2 px-2.5" x-text="alloc.boxes_placed + ' cjs · Cama ' + alloc.layer_index + '/' + alloc.max_layers_operational"></td>
                                                        <td class="py-2 px-2.5 font-bold text-orange-600" x-text="alloc.projected_rack_slot"></td>
                                                        <td class="py-2 px-2.5">
                                                            <span x-text="alloc.projected_total_weight_kg + ' kg (' + alloc.weight_fill_pct + '%)'"></span>
                                                            <span class="block text-[10px] text-zinc-500" x-text="alloc.projected_height_m + ' m altura'"></span>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Alertas Normativas Sobrias (NOM-036, Líquidos, Acomodo Posterior) -->
                                    <div class="space-y-1.5 text-xs">
                                        <div x-show="recommendation.ergonomics.alert_message"
                                             class="p-2.5 rounded border border-amber-300 bg-amber-50 text-amber-950 flex items-start space-x-2">
                                            <i class="fa-solid fa-triangle-exclamation text-amber-600 mt-0.5"></i>
                                            <span x-text="recommendation.ergonomics.alert_message"></span>
                                        </div>

                                        <div x-show="recommendation.product.is_liquid"
                                             class="p-2.5 rounded border border-red-300 bg-red-50 text-red-950 flex items-start space-x-2">
                                            <i class="fa-solid fa-flask text-red-600 mt-0.5"></i>
                                            <span x-text="recommendation.product.orientation_rule === 'HORIZONTAL_TINTA' ? 'Líquido/tinta: estibar en posición HORIZONTAL y segregado del papel.' : 'Líquido: mantener VERTICAL (flechas arriba) y en Nivel 1 sin papel debajo.'"></span>
                                        </div>

                                        <template x-for="(adv, i) in recommendation.lookahead.advisories" :key="i">
                                            <div class="p-2.5 rounded border border-zinc-300 bg-white text-zinc-800 flex items-start space-x-2">
                                                <i class="fa-solid fa-clock-rotate-left text-orange-500 mt-0.5"></i>
                                                <span x-text="adv"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Botones de Acción del Formulario -->
                        <div class="pt-3 border-t border-zinc-200 flex flex-wrap items-center justify-end gap-2">
                            <a :href="buildQrLabelUrl()"
                               target="_blank"
                               class="px-3 py-2 rounded border border-zinc-300 bg-white hover:border-zinc-950 text-zinc-800 text-xs font-bold uppercase transition">
                                <i class="fa-solid fa-qrcode mr-1 text-orange-500"></i> Etiquetas QR
                            </a>

                            <button type="button"
                                    @click="quickQueueArrival()"
                                    :disabled="busy"
                                    class="px-3.5 py-2 rounded bg-zinc-950 hover:bg-zinc-800 text-white text-xs font-bold uppercase transition cursor-pointer">
                                Encolar Llegada Posterior
                            </button>

                            <button type="button"
                                    @click="commitReceive()"
                                    :disabled="busy"
                                    class="px-4 py-2 rounded bg-orange-500 hover:bg-orange-600 disabled:bg-zinc-300 text-white text-xs font-bold uppercase tracking-wider transition cursor-pointer">
                                <i class="fa-solid fa-floppy-disk mr-1"></i> Confirmar e Ingresar a Almacén
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLAS DE CONTROL OPERATIVO: 1) Cola de Llegadas Posteriores y 2) Tarimas en Piso -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

            <!-- Tabla 1 (7/12): Cola de Llegadas Posteriores Programadas -->
            <div class="lg:col-span-7 bg-white border border-zinc-200 rounded-lg overflow-hidden shadow-2xs">
                <div class="px-5 py-3.5 border-b border-zinc-200 bg-zinc-50/60 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                            Cola de Llegadas Posteriores (<span x-text="state.planned_arrivals.length"></span> partidas)
                        </h3>
                        <p class="text-[11px] text-zinc-500">
                            Ordenada automáticamente por prioridad de estiba (1 Base → 2 Intermedio → 3 Superior).
                        </p>
                    </div>
                    <button x-show="state.planned_arrivals.length > 0"
                            type="button"
                            @click="executeBatchSequence()"
                            :disabled="busy"
                            class="px-3 py-1.5 rounded bg-orange-500 hover:bg-orange-600 text-white text-[11px] font-mono font-bold uppercase transition cursor-pointer self-start">
                        <i class="fa-solid fa-layer-group mr-1"></i> Acomodar Todo el Lote
                    </button>
                </div>

                <div x-show="state.planned_arrivals.length === 0" class="py-10 text-center text-xs font-mono text-zinc-500">
                    No hay llegadas pendientes para esta sucursal.
                </div>

                <div x-show="state.planned_arrivals.length > 0" class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse font-mono tabular-nums">
                        <thead>
                            <tr class="bg-zinc-950 text-white text-[10px] uppercase">
                                <th class="py-2.5 px-3">Orden</th>
                                <th class="py-2.5 px-3">SKU / Producto</th>
                                <th class="py-2.5 px-3">Zona Tarima</th>
                                <th class="py-2.5 px-3">Cajas / Peso</th>
                                <th class="py-2.5 px-3">Andén</th>
                                <th class="py-2.5 px-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            <template x-for="(arr, idx) in state.planned_arrivals" :key="arr.id">
                                <tr class="even:bg-zinc-50/80 hover:bg-orange-50/40 transition">
                                    <td class="py-2.5 px-3 font-bold text-zinc-700" x-text="'#' + (idx + 1)"></td>
                                    <td class="py-2.5 px-3 font-sans">
                                        <strong class="font-mono text-zinc-950" x-text="arr.sku"></strong>
                                        <div class="text-zinc-600 truncate max-w-[200px]" x-text="arr.product_name"></div>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                              :class="zoneBadgeClass(arr.zone_priority)"
                                              x-text="arr.zone_priority + ' · ' + arr.stow_zone"></span>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <strong class="text-zinc-950" x-text="arr.pending_boxes + ' cajas'"></strong>
                                        <span class="block text-[10px] text-zinc-500" x-text="arr.pending_weight_kg + ' kg'"></span>
                                    </td>
                                    <td class="py-2.5 px-3" x-text="arr.dock_code"></td>
                                    <td class="py-2.5 px-3 text-right whitespace-nowrap space-x-1">
                                        <a :href="routes.qrLabel + '?arrival_id=' + arr.id" target="_blank"
                                           class="inline-block px-2 py-1 rounded border border-zinc-300 bg-white hover:border-zinc-950 text-zinc-800 text-[10px] font-bold">
                                            QR
                                        </a>
                                        <button type="button" @click="receivePlannedLine(arr)" :disabled="busy"
                                                class="px-2.5 py-1 rounded bg-zinc-950 hover:bg-orange-500 text-white text-[10px] font-bold uppercase cursor-pointer">
                                            Ingresar
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tabla 2 (5/12): Tarimas Abiertas en Piso (En Armado) -->
            <div class="lg:col-span-5 bg-white border border-zinc-200 rounded-lg overflow-hidden shadow-2xs">
                <div class="px-5 py-3.5 border-b border-zinc-200 bg-zinc-50/60 flex items-center justify-between">
                    <div>
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                            Tarimas Abiertas en Piso (<span x-text="state.open_pallets.length"></span>)
                        </h3>
                        <p class="text-[11px] text-zinc-500">Consolidación estratificada antes de subir a rack.</p>
                    </div>
                </div>

                <div x-show="state.open_pallets.length === 0" class="py-10 text-center text-xs font-mono text-zinc-500">
                    No hay tarimas abiertas en piso.
                </div>

                <div x-show="state.open_pallets.length > 0" class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse font-mono tabular-nums">
                        <thead>
                            <tr class="bg-zinc-950 text-white text-[10px] uppercase">
                                <th class="py-2.5 px-3">Tarima</th>
                                <th class="py-2.5 px-3">Peso / Altura</th>
                                <th class="py-2.5 px-3">Cajas</th>
                                <th class="py-2.5 px-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            <template x-for="pallet in state.open_pallets" :key="pallet.id">
                                <tr class="even:bg-zinc-50/80 hover:bg-orange-50/40 transition">
                                    <td class="py-2.5 px-3">
                                        <strong class="text-zinc-950" x-text="pallet.code"></strong>
                                        <span class="block text-[10px] font-sans text-zinc-500" x-text="pallet.pallet_profile"></span>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <strong class="text-zinc-950" x-text="pallet.total_weight_kg + ' / 1000 kg'"></strong>
                                        <span class="block text-[10px] text-zinc-500" x-text="pallet.estimated_height_m + ' m · ' + pallet.volume_m3 + ' m³'"></span>
                                    </td>
                                    <td class="py-2.5 px-3" x-text="pallet.box_count + ' cajas'"></td>
                                    <td class="py-2.5 px-3 text-right whitespace-nowrap space-x-1">
                                        <button type="button" @click="openPalletModal(pallet)"
                                                class="px-2 py-1 rounded border border-zinc-300 bg-white hover:border-zinc-950 text-zinc-800 text-[10px] font-bold uppercase cursor-pointer">
                                            Ver Ficha
                                        </button>
                                        <button type="button" @click="slotOpenPallet(pallet.id)" :disabled="busy"
                                                class="px-2.5 py-1 rounded bg-orange-500 hover:bg-orange-600 text-white text-[10px] font-bold uppercase cursor-pointer">
                                            Ubicar
                                        </button>
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
    <!-- SECCIÓN 02: INVENTARIO Y MAPA DE RACK 4×8                             -->
    <!-- ===================================================================== -->
    <div x-show="tab === 'rack'" x-cloak class="space-y-5">
        <!-- Mapa Administrativo 4×8 del Rack -->
        <div class="bg-white border border-zinc-200 rounded-lg p-5 shadow-2xs">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-4 mb-4 border-b border-zinc-200">
                <div>
                    <span class="text-[10px] font-mono font-bold uppercase text-orange-600">ELEVACIÓN FRONTAL 4 NIVELES × 8 POSICIONES</span>
                    <h2 class="text-base font-extrabold text-zinc-950" x-text="state.branch.rack_code + ' · Destino ' + state.branch.code + ' ' + state.branch.name"></h2>
                    <p class="text-xs text-zinc-500">Haz clic en cualquier ubicación ocupada para abrir la <strong>Ficha de Tarima</strong> con el desglose de productos almacenados.</p>
                </div>
                <div class="flex flex-wrap gap-2 font-mono text-[10px]">
                    <span class="px-2.5 py-1 rounded bg-zinc-950 text-orange-400 font-bold">P5–P8 · OLA 1 (SALIDA TORTÓN)</span>
                    <span class="px-2.5 py-1 rounded bg-zinc-100 border border-zinc-300 text-zinc-700 font-bold">P1–P4 · OLA 2 (POSTERIOR)</span>
                    <span class="px-2.5 py-1 rounded bg-red-50 border border-red-300 text-red-800 font-bold">LÍQUIDOS EN NIVEL 1</span>
                </div>
            </div>

            <div class="overflow-x-auto space-y-2 font-mono tabular-nums">
                <div class="grid grid-cols-9 gap-2 min-w-[780px] text-[10px] text-center font-bold">
                    <div class="text-left text-zinc-500 py-1">NIVEL / POS</div>
                    <div class="bg-zinc-100 text-zinc-700 rounded py-1">P1 · Oeste</div>
                    <div class="bg-zinc-100 text-zinc-700 rounded py-1">P2 · Oeste</div>
                    <div class="bg-zinc-100 text-zinc-700 rounded py-1">P3 · Oeste</div>
                    <div class="bg-zinc-100 text-zinc-700 rounded py-1">P4 · Oeste</div>
                    <div class="bg-orange-50 border border-orange-300 text-orange-900 rounded py-1">P5 · Salida</div>
                    <div class="bg-orange-50 border border-orange-300 text-orange-900 rounded py-1">P6 · Salida</div>
                    <div class="bg-orange-50 border border-orange-300 text-orange-900 rounded py-1">P7 · Salida</div>
                    <div class="bg-orange-100 border border-orange-400 text-orange-950 rounded py-1">P8 · E1–E3 →</div>
                </div>

                <template x-for="level in [4, 3, 2, 1]" :key="level">
                    <div class="grid grid-cols-9 gap-2 min-w-[780px] items-stretch">
                        <div class="col-span-1 flex flex-col justify-center pr-2 border-r border-zinc-200">
                            <div class="text-xs font-extrabold text-zinc-950" x-text="'NIVEL ' + level"></div>
                            <div class="text-[10px] text-zinc-500" x-text="level === 1 ? 'Piso pesado' : 'Estiba'"></div>
                        </div>

                        <template x-for="pos in [1, 2, 3, 4, 5, 6, 7, 8]" :key="pos">
                            <button type="button"
                                    @click="if (getRackPallet(level, pos)) { openPalletModal(getRackPallet(level, pos)); }"
                                    class="p-2 rounded border text-left transition flex flex-col justify-between min-h-[76px]"
                                    :class="getRackPallet(level, pos)
                                        ? (getRackPallet(level, pos).has_liquids ? 'bg-red-50 border-red-400 hover:bg-red-100 cursor-pointer' : 'bg-zinc-950 text-white border-zinc-950 hover:border-orange-500 cursor-pointer')
                                        : 'bg-zinc-50 border-dashed border-zinc-300 text-zinc-400 cursor-default'">
                                <div class="flex items-center justify-between text-[10px]">
                                    <span class="font-bold" x-text="'N' + level + '·P' + pos"></span>
                                    <template x-if="getRackPallet(level, pos)">
                                        <span class="px-1 rounded text-[8px] font-bold"
                                              :class="getRackPallet(level, pos).has_liquids ? 'bg-red-600 text-white' : 'bg-orange-500 text-zinc-950'"
                                              x-text="getRackPallet(level, pos).has_liquids ? 'LIQ' : ('OLA ' + getRackPallet(level, pos).wave_number)"></span>
                                    </template>
                                </div>

                                <template x-if="getRackPallet(level, pos)">
                                    <div class="mt-1">
                                        <div class="text-[10px] font-extrabold truncate" x-text="getRackPallet(level, pos).code"></div>
                                        <div class="text-[11px] font-bold" :class="getRackPallet(level, pos).has_liquids ? 'text-red-800' : 'text-orange-400'"
                                             x-text="getRackPallet(level, pos).total_weight_kg + ' kg'"></div>
                                        <div class="text-[9px] opacity-75" x-text="getRackPallet(level, pos).box_count + ' cajas'"></div>
                                    </div>
                                </template>

                                <template x-if="!getRackPallet(level, pos)">
                                    <div class="mt-2 text-[10px]">LIBRE</div>
                                </template>
                            </button>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <!-- Tabla Administrativa de Tarimas Ubicadas en el Rack -->
        <div class="bg-white border border-zinc-200 rounded-lg overflow-hidden shadow-2xs">
            <div class="px-5 py-3.5 border-b border-zinc-200 bg-zinc-50/60 flex items-center justify-between">
                <div>
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                        Listado de Tarimas Ubicadas en <span x-text="state.branch.rack_code"></span> (<span x-text="slottedPalletsList.length"></span>)
                    </h3>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse font-mono tabular-nums">
                    <thead>
                        <tr class="bg-zinc-950 text-white text-[10px] uppercase">
                            <th class="py-2.5 px-4">Folio Tarima</th>
                            <th class="py-2.5 px-3">Coordenada Rack</th>
                            <th class="py-2.5 px-3">Ola Embarque</th>
                            <th class="py-2.5 px-3">Perfil Estiba</th>
                            <th class="py-2.5 px-3">Cajas / Peso Total</th>
                            <th class="py-2.5 px-3">Volumen / Altura</th>
                            <th class="py-2.5 px-4 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        <template x-for="pal in slottedPalletsList" :key="pal.id">
                            <tr class="even:bg-zinc-50/80 hover:bg-orange-50/40 transition">
                                <td class="py-2.5 px-4 font-bold text-zinc-950" x-text="pal.code"></td>
                                <td class="py-2.5 px-3 font-bold text-orange-600" x-text="state.branch.rack_code + ' · ' + pal.coordinate"></td>
                                <td class="py-2.5 px-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border"
                                          :class="pal.wave_number === 1 ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : 'bg-zinc-100 border-zinc-300 text-zinc-700'"
                                          x-text="'OLA ' + pal.wave_number"></span>
                                </td>
                                <td class="py-2.5 px-3 text-zinc-700" x-text="pal.pallet_profile"></td>
                                <td class="py-2.5 px-3" x-text="pal.box_count + ' cajas · ' + pal.total_weight_kg + ' kg'"></td>
                                <td class="py-2.5 px-3" x-text="pal.volume_m3 + ' m³ · ' + pal.estimated_height_m + ' m'"></td>
                                <td class="py-2.5 px-4 text-right">
                                    <button type="button" @click="openPalletModal(pal)"
                                            class="px-2.5 py-1 rounded border border-zinc-300 bg-white hover:border-zinc-950 text-zinc-800 text-[10px] font-bold uppercase cursor-pointer">
                                        Ver Ficha
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- SECCIÓN 03: ÓRDENES DE SALIDA (TORTÓN 12 TARIMAS)                     -->
    <!-- ===================================================================== -->
    <div x-show="tab === 'salidas'" x-cloak class="space-y-5">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <!-- Tabla de los 32 Destinos CDMX (5/12) -->
            <div class="lg:col-span-5 bg-white border border-zinc-200 rounded-lg overflow-hidden shadow-2xs">
                <div class="px-4 py-3 border-b border-zinc-200 bg-zinc-50/60">
                    <h2 class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                        Consolidación por Destino CDMX (32 Racks)
                    </h2>
                </div>
                <div class="max-h-[480px] overflow-y-auto">
                    <table class="w-full text-left text-xs border-collapse font-mono tabular-nums">
                        <thead class="sticky top-0">
                            <tr class="bg-zinc-950 text-white text-[10px] uppercase">
                                <th class="py-2 px-3">Destino</th>
                                <th class="py-2 px-2">Tarimas</th>
                                <th class="py-2 px-2">Avance</th>
                                <th class="py-2 px-3 text-right">Estatus</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            <template x-for="b in sortedBranchesByReadiness" :key="b.id">
                                <tr @click="selectedBranchId = b.id; loadBranchState(b.id)"
                                    class="cursor-pointer transition"
                                    :class="Number(selectedBranchId) === Number(b.id) ? 'bg-orange-50 font-bold' : 'even:bg-zinc-50/80 hover:bg-zinc-100'">
                                    <td class="py-2 px-3 font-sans">
                                        <span class="font-mono font-bold text-zinc-950" x-text="b.code"></span>
                                        <span class="text-zinc-700 block text-[11px] truncate max-w-[140px]" x-text="b.name"></span>
                                    </td>
                                    <td class="py-2 px-2" x-text="b.slotted_count + '/32'"></td>
                                    <td class="py-2 px-2" x-text="b.weight_pct + '%'"></td>
                                    <td class="py-2 px-3 text-right">
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold border"
                                              :class="b.ready_for_torton ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : 'bg-zinc-100 border-zinc-300 text-zinc-600'"
                                              x-text="b.ready_for_torton ? 'LISTA ≥90%' : 'ACUMULANDO'"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Panel Administrativo de Emisión de Orden de Salida (7/12) -->
            <div class="lg:col-span-7 bg-white border border-zinc-200 rounded-lg p-5 space-y-4 shadow-2xs">
                <div class="flex items-center justify-between border-b border-zinc-200 pb-3">
                    <div>
                        <span class="text-[10px] font-mono font-bold uppercase text-orange-600">EMISIÓN DE ORDEN DE SALIDA TORTÓN (12 TARIMAS)</span>
                        <h2 class="text-lg font-extrabold text-zinc-950" x-text="state.branch.code + ' · ' + state.branch.name"></h2>
                    </div>
                    <span class="px-2.5 py-1 rounded text-xs font-mono font-bold border"
                          :class="state.dispatch_plan.ready_for_torton ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : 'bg-zinc-100 border-zinc-300 text-zinc-700'"
                          x-text="state.dispatch_plan.ready_for_torton ? '● LIBERACIÓN AUTORIZADA (≥90%)' : '● CANDADO ACTIVO (<90%)'"></span>
                </div>

                <!-- Medidores de Peso y Volumen -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-zinc-50 border border-zinc-200 rounded-md p-4 font-mono tabular-nums text-xs">
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-zinc-600">Peso: <strong class="text-zinc-950" x-text="Number(state.dispatch_plan.total_weight_kg).toLocaleString('es-MX') + ' / 12,000 kg'"></strong></span>
                            <strong :class="state.dispatch_plan.weight_pct >= 90 ? 'text-emerald-700' : 'text-zinc-700'" x-text="state.dispatch_plan.weight_pct + '%'"></strong>
                        </div>
                        <div class="w-full h-2.5 bg-zinc-200 rounded-xs overflow-hidden">
                            <div class="h-full" :class="state.dispatch_plan.weight_pct >= 90 ? 'bg-orange-500' : 'bg-zinc-600'"
                                 :style="'width:' + Math.min(100, state.dispatch_plan.weight_pct) + '%'"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-zinc-600">Volumen: <strong class="text-zinc-950" x-text="state.dispatch_plan.total_volume_m3 + ' / 28.59 m³'"></strong></span>
                            <strong :class="state.dispatch_plan.volume_pct >= 90 ? 'text-emerald-700' : 'text-zinc-700'" x-text="state.dispatch_plan.volume_pct + '%'"></strong>
                        </div>
                        <div class="w-full h-2.5 bg-zinc-200 rounded-xs overflow-hidden">
                            <div class="h-full" :class="state.dispatch_plan.volume_pct >= 90 ? 'bg-orange-500' : 'bg-zinc-600'"
                                 :style="'width:' + Math.min(100, state.dispatch_plan.volume_pct) + '%'"></div>
                        </div>
                    </div>
                </div>

                <!-- Datos de Unidad en Cuadrícula de 3 Columnas -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Andén de Salida</label>
                        <select x-model="outboundForm.dock_code" class="w-full bg-white border border-zinc-300 rounded px-3 py-2 font-mono font-bold">
                            <option value="E1">Andén E1</option>
                            <option value="E2">Andén E2</option>
                            <option value="E3">Andén E3</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Placas Unidad Tortón</label>
                        <input type="text" x-model="outboundForm.unit_plate" class="w-full bg-white border border-zinc-300 rounded px-3 py-2 font-mono">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Operador de Transporte</label>
                        <input type="text" x-model="outboundForm.driver_name" class="w-full bg-white border border-zinc-300 rounded px-3 py-2">
                    </div>
                </div>

                <!-- Tabla de Secuencia de Extracción (Rack → Posición Tortón T-01..T-12) -->
                <div class="border border-zinc-200 rounded overflow-hidden">
                    <div class="bg-zinc-100 px-3 py-2 border-b border-zinc-200 flex justify-between text-[10px] font-mono font-bold uppercase text-zinc-700">
                        <span>Secuencia de Extracción de Rack y Balanceo de Ejes (T-01 a T-12)</span>
                        <span x-text="state.dispatch_plan.selected_pallet_count + ' tarimas asignadas'"></span>
                    </div>
                    <div class="max-h-48 overflow-y-auto">
                        <table class="w-full text-left text-xs border-collapse font-mono tabular-nums">
                            <thead>
                                <tr class="bg-zinc-50 border-b border-zinc-200 text-[10px] uppercase text-zinc-600">
                                    <th class="py-1.5 px-3">Paso</th>
                                    <th class="py-1.5 px-3">Tarima</th>
                                    <th class="py-1.5 px-3">Origen Rack</th>
                                    <th class="py-1.5 px-3">Posición Tortón</th>
                                    <th class="py-1.5 px-3">Peso</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200">
                                <template x-for="st in state.dispatch_plan.loading_sequence" :key="st.step">
                                    <tr class="even:bg-zinc-50/70">
                                        <td class="py-1.5 px-3 font-bold" x-text="'#' + st.step"></td>
                                        <td class="py-1.5 px-3 font-bold text-zinc-950" x-text="st.pallet_code"></td>
                                        <td class="py-1.5 px-3" x-text="st.from_rack"></td>
                                        <td class="py-1.5 px-3 font-bold text-orange-600" x-text="st.truck_slot_label + ' (' + st.axle_zone + ')'"></td>
                                        <td class="py-1.5 px-3" x-text="st.weight_kg + ' kg'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Excepción Gerencial si <90% -->
                <div x-show="!state.dispatch_plan.ready_for_torton && state.dispatch_plan.selected_pallet_count > 0"
                     class="p-3 rounded bg-zinc-50 border border-zinc-300 space-y-2 text-xs">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" x-model="outboundForm.exception_authorized" class="rounded">
                        <span class="font-bold text-zinc-900">Autorizar despacho con Excepción Gerencial (&lt;90%)</span>
                    </label>
                    <input x-show="outboundForm.exception_authorized" x-cloak
                           type="text" x-model="outboundForm.exception_reason"
                           placeholder="Motivo auditable de la excepción..."
                           class="w-full bg-white border border-zinc-300 rounded px-3 py-1.5 text-xs">
                </div>

                <button type="button"
                        @click="emitOutboundOrder()"
                        :disabled="busy || state.dispatch_plan.selected_pallet_count === 0 || (!state.dispatch_plan.ready_for_torton && !outboundForm.exception_authorized)"
                        class="w-full py-3 rounded-md bg-orange-500 hover:bg-orange-600 disabled:bg-zinc-300 text-white text-xs font-bold uppercase tracking-wider transition cursor-pointer">
                    <i class="fa-solid fa-file-export mr-1.5"></i> Emitir Orden de Salida, Liberar Rack y Generar Manifiesto QR
                </button>
            </div>
        </div>

        <!-- Historial de Órdenes de Salida -->
        <div class="bg-white border border-zinc-200 rounded-lg overflow-hidden shadow-2xs">
            <div class="px-5 py-3.5 border-b border-zinc-200 bg-zinc-50/60">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                    Padrón de Órdenes de Salida Emitidas
                </h3>
            </div>
            <div x-show="state.outbound_orders.length === 0" class="py-8 text-center text-xs font-mono text-zinc-500">
                Aún no se han emitido órdenes de salida en esta sesión.
            </div>
            <div x-show="state.outbound_orders.length > 0" class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse font-mono tabular-nums">
                    <thead>
                        <tr class="bg-zinc-950 text-white text-[10px] uppercase">
                            <th class="py-2.5 px-4">Folio Salida</th>
                            <th class="py-2.5 px-3">Emisión</th>
                            <th class="py-2.5 px-3">Sucursal Destino</th>
                            <th class="py-2.5 px-3">Andén / Unidad</th>
                            <th class="py-2.5 px-3">Tarimas</th>
                            <th class="py-2.5 px-3">Ocupación Tortón</th>
                            <th class="py-2.5 px-4 text-right">Documento</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        <template x-for="ord in state.outbound_orders" :key="ord.id">
                            <tr class="even:bg-zinc-50/80 hover:bg-orange-50/40">
                                <td class="py-2.5 px-4 font-bold text-zinc-950" x-text="ord.folio"></td>
                                <td class="py-2.5 px-3 text-zinc-600" x-text="ord.dispatched_at"></td>
                                <td class="py-2.5 px-3 font-sans font-bold text-zinc-900" x-text="ord.branch_code + ' · ' + ord.branch_name"></td>
                                <td class="py-2.5 px-3" x-text="ord.dock_code + ' · ' + ord.unit_plate"></td>
                                <td class="py-2.5 px-3" x-text="ord.pallet_count + ' / 12'"></td>
                                <td class="py-2.5 px-3 font-bold text-emerald-700" x-text="ord.weight_pct + '% P · ' + ord.volume_pct + '% V'"></td>
                                <td class="py-2.5 px-4 text-right">
                                    <a :href="ord.manifest_url" target="_blank"
                                       class="inline-block px-2.5 py-1 rounded bg-zinc-950 hover:bg-orange-500 text-white text-[10px] font-bold uppercase">
                                        Manifiesto QR
                                    </a>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- SECCIÓN 04: CATÁLOGO MAESTRO DE PRODUCTOS (CON FICHA TÉCNICA)         -->
    <!-- ===================================================================== -->
    <div x-show="tab === 'catalogo'" x-cloak class="space-y-4">
        <!-- Barra de Búsqueda y Filtros -->
        <div class="bg-white border border-zinc-200 rounded-lg p-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="relative flex-1 max-w-md">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-zinc-400 text-xs"></i>
                <input type="text" x-model="catalogSearch" placeholder="Buscar por código SKU, nombre o categoría..."
                       class="w-full bg-zinc-50 border border-zinc-300 rounded-md pl-8 pr-4 py-1.5 text-xs focus:bg-white focus:outline-none focus:border-orange-500">
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs font-mono">
                <select x-model="catalogZoneFilter" class="bg-white border border-zinc-300 rounded px-2.5 py-1.5 text-xs">
                    <option value="ALL">Todas las Zonas</option>
                    <option value="1">1 · Base / Pesado</option>
                    <option value="2">2 · Intermedio / Estándar</option>
                    <option value="3">3 · Superior / Ligero</option>
                </select>

                <button type="button"
                        @click="catalogSortMode = (catalogSortMode === 'commercial' ? 'wms' : 'commercial')"
                        class="px-3 py-1.5 rounded bg-zinc-100 border border-zinc-300 hover:border-zinc-950 text-zinc-800 text-[11px] font-bold uppercase cursor-pointer">
                    <span x-text="catalogSortMode === 'wms' ? 'Orden: Algoritmo WMS' : 'Orden: Peso Unitario ↑'"></span>
                </button>

                <button type="button" @click="showNewProductModal = true"
                        class="px-3 py-1.5 rounded bg-orange-500 hover:bg-orange-600 text-white text-[11px] font-bold uppercase cursor-pointer">
                    + Alta de Producto
                </button>
            </div>
        </div>

        <!-- Tabla Principal Cebrada del Catálogo -->
        <div class="bg-white border border-zinc-200 rounded-lg overflow-hidden shadow-2xs">
            <div class="overflow-x-auto max-h-[580px] overflow-y-auto">
                <table class="w-full text-left text-xs border-collapse font-mono tabular-nums">
                    <thead class="sticky top-0 z-10">
                        <tr class="bg-zinc-950 text-white text-[10px] uppercase">
                            <th class="py-3 px-3">#</th>
                            <th class="py-3 px-3">SKU</th>
                            <th class="py-3 px-3">Descripción del Producto</th>
                            <th class="py-3 px-3">Categoría</th>
                            <th class="py-3 px-3">Caja Máster</th>
                            <th class="py-3 px-3">Densidad / ECT</th>
                            <th class="py-3 px-3">Zona de Estiba</th>
                            <th class="py-3 px-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        <template x-for="p in filteredCatalog" :key="p.id">
                            <tr class="even:bg-zinc-50/80 hover:bg-orange-50/40 transition">
                                <td class="py-2.5 px-3 text-zinc-400" x-text="p.catalog_order"></td>
                                <td class="py-2.5 px-3 font-extrabold text-zinc-950" x-text="p.sku"></td>
                                <td class="py-2.5 px-3 font-sans">
                                    <div class="font-bold text-zinc-900" x-text="p.name"></div>
                                    <div class="text-[11px] text-zinc-500" x-text="'Empaque: ×' + p.master_factor + ' (' + p.commercial_presentation + ')'"></div>
                                </td>
                                <td class="py-2.5 px-3 font-sans text-zinc-700" x-text="p.category"></td>
                                <td class="py-2.5 px-3">
                                    <strong class="text-zinc-950" x-text="Number(p.master_weight_kg).toFixed(2) + ' kg'"></strong>
                                    <span class="block text-[10px] text-zinc-500" x-text="Number(p.master_volume_m3).toFixed(4) + ' m³'"></span>
                                </td>
                                <td class="py-2.5 px-3" x-text="p.master_density_kg_m3 + ' kg/m³ · ' + p.ect_rating + ' ECT'"></td>
                                <td class="py-2.5 px-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                          :class="zoneBadgeClass(p.zone_priority)"
                                          x-text="p.zone_priority + ' · ' + p.stow_zone"></span>
                                </td>
                                <td class="py-2.5 px-3 text-right whitespace-nowrap space-x-1">
                                    <button type="button" @click="selectedProductDetail = p"
                                            class="px-2 py-1 rounded border border-zinc-300 bg-white hover:border-zinc-950 text-zinc-800 text-[10px] font-bold uppercase cursor-pointer">
                                        Ver Ficha
                                    </button>
                                    <button type="button" @click="selectProduct(p); showEntryForm = true; tab = 'llegadas'"
                                            class="px-2.5 py-1 rounded bg-zinc-950 hover:bg-orange-500 text-white text-[10px] font-bold uppercase cursor-pointer">
                                        Ingresar
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL 1: FICHA DE TARIMA (DETALLE DE PRODUCTOS ALMACENADOS)           -->
    <!-- ===================================================================== -->
    <div x-show="modalPallet !== null" x-cloak class="fixed inset-0 z-50 bg-zinc-950/75 flex items-center justify-center p-4">
        <template x-if="modalPallet">
            <div @click.outside="modalPallet = null" class="bg-white text-zinc-950 border-2 border-zinc-950 rounded-lg max-w-2xl w-full overflow-hidden shadow-2xl">
                <div class="bg-zinc-950 text-white px-6 py-4 flex items-center justify-between">
                    <div>
                        <span class="px-2 py-0.5 rounded bg-orange-500 text-zinc-950 font-mono text-[10px] font-extrabold uppercase">
                            FICHA TÉCNICA DE TARIMA
                        </span>
                        <h3 class="text-lg font-extrabold text-white mt-1"
                            x-text="modalPallet.code + ' · ' + (modalPallet.coordinate ? (state.branch.rack_code + ' ' + modalPallet.coordinate) : 'EN PISO (ARMADO)')"></h3>
                    </div>
                    <button type="button" @click="modalPallet = null" class="text-zinc-400 hover:text-white cursor-pointer">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 font-mono bg-zinc-50 border border-zinc-200 rounded p-3">
                        <div>
                            <span class="text-[10px] text-zinc-500 block">PESO TOTAL</span>
                            <strong class="text-sm text-zinc-950" x-text="modalPallet.total_weight_kg + ' / 1,000 kg'"></strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">VOLUMEN ÚTIL</span>
                            <strong class="text-sm text-zinc-950" x-text="modalPallet.volume_m3 + ' / 1.95 m³'"></strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">ALTURA ESTIBA</span>
                            <strong class="text-sm text-zinc-950" x-text="modalPallet.estimated_height_m + ' / 1.85 m'"></strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">CAJAS MÁSTER</span>
                            <strong class="text-sm text-zinc-950" x-text="modalPallet.box_count + ' cajas'"></strong>
                        </div>
                    </div>

                    <div class="border border-zinc-200 rounded overflow-hidden">
                        <table class="w-full text-left text-xs border-collapse font-mono tabular-nums">
                            <thead>
                                <tr class="bg-zinc-950 text-white text-[10px] uppercase">
                                    <th class="py-2 px-3">Piso Tarima</th>
                                    <th class="py-2 px-3">SKU / Producto</th>
                                    <th class="py-2 px-3">Cajas · Cama</th>
                                    <th class="py-2 px-3">Peso</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200">
                                <template x-for="item in modalPallet.items" :key="item.id">
                                    <tr class="even:bg-zinc-50">
                                        <td class="py-2 px-3">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                                  :class="zoneBadgeClass(item.zone_priority)"
                                                  x-text="item.zone_priority + ' · ' + item.stow_zone"></span>
                                        </td>
                                        <td class="py-2 px-3 font-sans">
                                            <strong class="font-mono text-zinc-950" x-text="item.sku"></strong>
                                            <div class="text-zinc-600" x-text="item.name"></div>
                                        </td>
                                        <td class="py-2 px-3" x-text="item.quantity_boxes + ' cajas · Cama ' + item.layer_index"></td>
                                        <td class="py-2 px-3" x-text="item.total_weight_kg + ' kg'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="px-6 py-3 bg-zinc-100 border-t border-zinc-200 flex justify-end">
                    <button type="button" @click="modalPallet = null"
                            class="px-4 py-1.5 rounded border border-zinc-300 bg-white text-xs font-bold uppercase cursor-pointer">
                        Cerrar Ficha
                    </button>
                </div>
            </div>
        </template>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL 2: FICHA TÉCNICA DE PRODUCTO DEL CATÁLOGO                       -->
    <!-- ===================================================================== -->
    <div x-show="selectedProductDetail !== null" x-cloak class="fixed inset-0 z-50 bg-zinc-950/75 flex items-center justify-center p-4">
        <template x-if="selectedProductDetail">
            <div @click.outside="selectedProductDetail = null" class="bg-white text-zinc-950 border-2 border-zinc-950 rounded-lg max-w-xl w-full overflow-hidden shadow-2xl">
                <div class="bg-zinc-950 text-white px-6 py-4 flex items-center justify-between">
                    <div>
                        <span class="px-2 py-0.5 rounded bg-orange-500 text-zinc-950 font-mono text-[10px] font-extrabold uppercase"
                              x-text="selectedProductDetail.sku + ' · ' + selectedProductDetail.category"></span>
                        <h3 class="text-base font-extrabold text-white mt-1" x-text="selectedProductDetail.name"></h3>
                    </div>
                    <button type="button" @click="selectedProductDetail = null" class="text-zinc-400 hover:text-white cursor-pointer">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs font-mono">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 bg-zinc-50 border border-zinc-200 rounded p-3.5">
                        <div>
                            <span class="text-[10px] text-zinc-500 block">PESO UNITARIO</span>
                            <strong x-text="selectedProductDetail.unit_weight_g + ' g'"></strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">FACTOR MÁSTER</span>
                            <strong x-text="'×' + selectedProductDetail.master_factor + ' (' + selectedProductDetail.commercial_presentation + ')'"></strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">PESO CAJA MÁSTER</span>
                            <strong x-text="selectedProductDetail.master_weight_kg + ' kg'"></strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">MEDIDAS CAJA (CM)</span>
                            <strong x-text="selectedProductDetail.master_length_cm + '×' + selectedProductDetail.master_width_cm + '×' + selectedProductDetail.master_height_cm"></strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">VOLUMEN / DENSIDAD</span>
                            <strong x-text="selectedProductDetail.master_volume_m3 + ' m³ · ' + selectedProductDetail.master_density_kg_m3 + ' kg/m³'"></strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">ECT / CAMAS MÁX</span>
                            <strong x-text="selectedProductDetail.ect_rating + ' ECT · ' + selectedProductDetail.max_layers_operational + ' camas'"></strong>
                        </div>
                    </div>

                    <div class="p-3 rounded border border-zinc-200 font-sans">
                        <div class="font-mono text-[10px] font-bold uppercase text-zinc-500 mb-1">Directriz de Almacenamiento y NOM-036</div>
                        <p class="text-zinc-800" x-text="selectedProductDetail.handling_notes || 'Almacenamiento estándar en ambiente seco (45–55% HR).'"></p>
                    </div>
                </div>

                <div class="px-6 py-3 bg-zinc-100 border-t border-zinc-200 flex justify-between">
                    <button type="button" @click="selectedProductDetail = null"
                            class="px-4 py-1.5 rounded border border-zinc-300 bg-white text-xs font-bold uppercase cursor-pointer">
                        Cerrar
                    </button>
                    <button type="button"
                            @click="selectProduct(selectedProductDetail); selectedProductDetail = null; showEntryForm = true; tab = 'llegadas'"
                            class="px-4 py-1.5 rounded bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold uppercase cursor-pointer">
                        Capturar Entrada de este Producto →
                    </button>
                </div>
            </div>
        </template>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL 3: ALTA DE NUEVO PRODUCTO EN EL CATÁLOGO                        -->
    <!-- ===================================================================== -->
    <div x-show="showNewProductModal" x-cloak class="fixed inset-0 z-50 bg-zinc-950/75 flex items-center justify-center p-4">
        <div @click.outside="showNewProductModal = false" class="bg-white text-zinc-950 border-2 border-zinc-950 rounded-lg max-w-xl w-full overflow-hidden shadow-2xl">
            <div class="bg-zinc-950 text-white px-6 py-4 flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-mono font-bold uppercase text-orange-400">CATÁLOGO MAESTRO · CEDIS CDMX</span>
                    <h3 class="text-base font-extrabold text-white">Alta de Nuevo Producto</h3>
                </div>
                <button type="button" @click="showNewProductModal = false" class="text-zinc-400 hover:text-white cursor-pointer">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <div class="p-6 space-y-4 text-xs">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Código SKU</label>
                        <input type="text" x-model="newProduct.sku" class="w-full bg-white border border-zinc-300 rounded px-3 py-2 font-mono font-bold uppercase">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Categoría Comercial</label>
                        <select x-model="newProduct.category" class="w-full bg-white border border-zinc-300 rounded px-3 py-2">
                            <option>Papel y cuadernos</option>
                            <option>Archivo y organización</option>
                            <option>Adhesivos y cintas</option>
                            <option>Escritura</option>
                            <option>Suministros de oficina</option>
                            <option>Corte y medición</option>
                            <option>Arte y escolar</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Descripción / Nombre del Producto</label>
                        <input type="text" x-model="newProduct.name" placeholder="Ej. Cuaderno Profesional Pasta Dura 150h"
                               class="w-full bg-white border border-zinc-300 rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Piezas por Caja Máster</label>
                        <input type="number" min="1" x-model.number="newProduct.master_factor" class="w-full bg-white border border-zinc-300 rounded px-3 py-2 font-mono font-bold">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Peso Caja Máster (kg)</label>
                        <input type="number" step="0.1" min="0.5" x-model.number="newProduct.master_weight_kg" class="w-full bg-white border border-zinc-300 rounded px-3 py-2 font-mono font-bold">
                    </div>
                    <div class="sm:col-span-2 grid grid-cols-3 gap-2 font-mono">
                        <div>
                            <label class="block text-[10px] font-sans font-bold uppercase text-zinc-600 mb-1">Largo (cm)</label>
                            <input type="number" min="10" x-model.number="newProduct.master_length_cm" class="w-full bg-white border border-zinc-300 rounded px-2.5 py-1.5">
                        </div>
                        <div>
                            <label class="block text-[10px] font-sans font-bold uppercase text-zinc-600 mb-1">Ancho (cm)</label>
                            <input type="number" min="10" x-model.number="newProduct.master_width_cm" class="w-full bg-white border border-zinc-300 rounded px-2.5 py-1.5">
                        </div>
                        <div>
                            <label class="block text-[10px] font-sans font-bold uppercase text-zinc-600 mb-1">Alto (cm)</label>
                            <input type="number" min="5" x-model.number="newProduct.master_height_cm" class="w-full bg-white border border-zinc-300 rounded px-2.5 py-1.5">
                        </div>
                    </div>
                </div>

                <div class="p-3 rounded bg-zinc-50 border border-zinc-200 grid grid-cols-3 gap-2">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" x-model="newProduct.is_liquid" class="rounded">
                        <span>Líquido / Tinta</span>
                    </label>
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" x-model="newProduct.is_hygroscopic" class="rounded">
                        <span>Papel (Higroscópico)</span>
                    </label>
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" x-model="newProduct.is_crushable" class="rounded">
                        <span>Frágil / Deformable</span>
                    </label>
                </div>

                <div class="p-3 rounded bg-zinc-950 text-white font-mono flex flex-wrap justify-between gap-2">
                    <span>Volumen: <strong class="text-orange-400" x-text="previewNewProductStats.volume + ' m³'"></strong></span>
                    <span>Densidad: <strong class="text-orange-400" x-text="previewNewProductStats.density + ' kg/m³'"></strong></span>
                    <span>Zona: <strong class="text-orange-400" x-text="previewNewProductStats.zone"></strong></span>
                </div>
            </div>

            <div class="px-6 py-3 bg-zinc-100 border-t border-zinc-200 flex justify-end space-x-2">
                <button type="button" @click="showNewProductModal = false" class="px-4 py-1.5 rounded border border-zinc-300 bg-white text-xs font-bold uppercase cursor-pointer">
                    Cancelar
                </button>
                <button type="button" @click="saveNewProduct()" :disabled="busy"
                        class="px-5 py-1.5 rounded bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold uppercase cursor-pointer">
                    Guardar Producto
                </button>
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL 4: LECTOR QR DE PRODUCTO                                        -->
    <!-- ===================================================================== -->
    <div x-show="showQrScannerModal" x-cloak class="fixed inset-0 z-50 bg-zinc-950/75 flex items-center justify-center p-4">
        <div @click.outside="stopQrCamera(); showQrScannerModal = false" class="bg-white text-zinc-950 border-2 border-zinc-950 rounded-lg max-w-lg w-full overflow-hidden shadow-2xl">
            <div class="bg-zinc-950 text-white px-6 py-4 flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-mono font-bold uppercase text-orange-400">LECTOR ÓPTICO 2D · CEDIS CDMX</span>
                    <h3 class="text-base font-extrabold text-white">Recepción por Etiqueta QR</h3>
                </div>
                <button type="button" @click="stopQrCamera(); showQrScannerModal = false" class="text-zinc-400 hover:text-white cursor-pointer">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <div class="p-6 space-y-4 text-xs">
                <div class="relative aspect-square max-h-52 mx-auto bg-zinc-950 rounded border border-zinc-300 overflow-hidden flex items-center justify-center">
                    <div id="wms-qr-reader" class="w-full h-full"></div>
                    <div x-show="qrCameraState !== 'scanning'" class="absolute inset-0 flex flex-col items-center justify-center p-4 text-center bg-zinc-950 text-white">
                        <i class="fa-solid fa-qrcode text-4xl text-orange-500 mb-2"></i>
                        <p class="text-xs text-zinc-300">Activa la cámara o confirma el código QR cargado abajo</p>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button x-show="qrCameraState !== 'scanning'" type="button" @click="startQrCamera()"
                            class="w-full py-2 rounded bg-zinc-950 text-white font-mono text-xs font-bold uppercase cursor-pointer">
                        <i class="fa-solid fa-camera mr-1"></i> Abrir Cámara
                    </button>
                    <button x-show="qrCameraState === 'scanning'" x-cloak type="button" @click="stopQrCamera()"
                            class="w-full py-2 rounded bg-zinc-200 text-zinc-950 font-mono text-xs font-bold uppercase cursor-pointer">
                        Detener Cámara
                    </button>
                </div>

                <div class="space-y-1.5 font-mono">
                    <label class="block text-[10px] font-bold uppercase text-zinc-600">JSON de Etiqueta QR</label>
                    <textarea x-model="qrPayloadInput" rows="3"
                              class="w-full bg-zinc-50 border border-zinc-300 rounded p-2 text-[11px] text-zinc-900"></textarea>
                </div>

                <div x-show="qrCameraError !== ''" x-cloak class="text-[11px] text-red-600" x-text="qrCameraError"></div>
            </div>

            <div class="px-6 py-3 bg-zinc-100 border-t border-zinc-200 flex justify-end space-x-2">
                <button type="button" @click="stopQrCamera(); showQrScannerModal = false" class="px-4 py-1.5 rounded border border-zinc-300 bg-white text-xs font-bold uppercase cursor-pointer">
                    Cerrar
                </button>
                <button type="button" @click="submitQrScan()" :disabled="busy"
                        class="px-5 py-1.5 rounded bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold uppercase cursor-pointer">
                    Procesar QR y Ubicar en Rack
                </button>
            </div>
        </div>
    </div>

</div>
@endsection

@section('extra-scripts')
<script>
function wmsSlottingApp(config) {
    return {
        csrfToken: config.csrfToken,
        routes: config.routes,
        branchesSummary: config.branchesSummary,
        products: config.products,
        state: config.initialState,
        selectedBranchId: config.initialState.branch.id,
        tab: 'llegadas',
        showEntryForm: true,
        busy: false,
        toastMessage: '',
        errorMessage: '',

        // Captura de producto
        receiveSearch: '',
        selectedCategory: 'ALL',
        selectedProductId: config.products.find(p => p.sku === 'PAP-007')?.id || config.products[0]?.id,
        receiveBoxes: 10,
        distanceBand: 'under_10m',
        recommendation: config.initialRecommendation || null,
        lastBatchResult: null,

        // Modales de inspección (Fichas)
        modalPallet: null,
        selectedProductDetail: null,
        showNewProductModal: false,
        showQrScannerModal: false,

        newProduct: {
            sku: 'PAP-011',
            name: '',
            category: 'Papel y cuadernos',
            commercial_presentation: '1 paquete',
            master_factor: 12,
            master_weight_kg: 14.5,
            master_length_cm: 44,
            master_width_cm: 30,
            master_height_cm: 22,
            is_liquid: false,
            is_hygroscopic: true,
            is_crushable: false
        },

        qrCameraState: 'idle',
        qrCameraError: '',
        qrInstance: null,
        qrPayloadInput: '',

        outboundForm: {
            dock_code: 'E1',
            unit_plate: '55-XA-2F',
            driver_name: 'Roberto Ruiz Hernández',
            exception_authorized: false,
            exception_reason: ''
        },

        catalogSearch: '',
        catalogZoneFilter: 'ALL',
        catalogSortMode: 'commercial',

        init() {
            this.fillQrPayloadFromSelection();
            if (!this.recommendation) {
                this.fetchRecommendation();
            }
        },

        get activeProduct() {
            return this.products.find(p => Number(p.id) === Number(this.selectedProductId)) || this.products[0] || null;
        },

        get slottedPalletsList() {
            return Object.values(this.state.rack_grid || {});
        },

        get categoriesList() {
            const counts = {};
            this.products.forEach(p => {
                counts[p.category] = (counts[p.category] || 0) + 1;
            });
            return [
                { key: 'ALL', label: 'Todas las categorías', count: this.products.length },
                { key: 'Papel y cuadernos', label: 'Papel y cuadernos', count: counts['Papel y cuadernos'] || 0 },
                { key: 'Archivo y organización', label: 'Archivo y organización', count: counts['Archivo y organización'] || 0 },
                { key: 'Adhesivos y cintas', label: 'Adhesivos y cintas', count: counts['Adhesivos y cintas'] || 0 },
                { key: 'Escritura', label: 'Escritura', count: counts['Escritura'] || 0 },
                { key: 'Suministros de oficina', label: 'Suministros de oficina', count: counts['Suministros de oficina'] || 0 },
                { key: 'Corte y medición', label: 'Corte y medición', count: counts['Corte y medición'] || 0 },
                { key: 'Arte y escolar', label: 'Arte y escolar', count: counts['Arte y escolar'] || 0 },
            ];
        },

        get filteredReceiveProducts() {
            const q = this.receiveSearch.trim().toLowerCase();
            const list = this.products.filter(p => {
                const matchCat = this.selectedCategory === 'ALL' || p.category === this.selectedCategory;
                const matchText = !q ||
                    p.sku.toLowerCase().includes(q) ||
                    p.name.toLowerCase().includes(q) ||
                    p.category.toLowerCase().includes(q) ||
                    p.stow_zone.toLowerCase().includes(q);
                return matchCat && matchText;
            });
            return list.length > 0 ? list : this.products;
        },

        onCategoryFilterChange() {
            const list = this.filteredReceiveProducts;
            if (list.length > 0 && !list.some(p => Number(p.id) === Number(this.selectedProductId))) {
                this.selectedProductId = list[0].id;
                this.onBoxQtyChanged();
            }
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

        get sortedBranchesByReadiness() {
            return [...this.branchesSummary].sort((a, b) => {
                if (a.ready_for_torton !== b.ready_for_torton) return b.ready_for_torton ? 1 : -1;
                return b.weight_pct - a.weight_pct;
            });
        },

        get filteredCatalog() {
            const q = this.catalogSearch.trim().toLowerCase();
            let list = this.products.filter(p => {
                const matchZone = this.catalogZoneFilter === 'ALL' || String(p.zone_priority) === this.catalogZoneFilter;
                const matchText = !q || p.sku.toLowerCase().includes(q) || p.name.toLowerCase().includes(q) || p.category.toLowerCase().includes(q);
                return matchZone && matchText;
            });

            if (this.catalogSortMode === 'wms') {
                list = [...list].sort((a, b) =>
                    (a.zone_priority - b.zone_priority) ||
                    (b.master_density_kg_m3 - a.master_density_kg_m3) ||
                    (b.master_weight_kg - a.master_weight_kg)
                );
            } else {
                list = [...list].sort((a, b) => a.catalog_order - b.catalog_order);
            }
            return list;
        },

        selectProduct(product) {
            this.selectedProductId = product.id;
            this.onBoxQtyChanged();
        },

        setBoxQty(qty) {
            this.receiveBoxes = Math.max(1, Math.min(500, Number(qty || 1)));
            this.onBoxQtyChanged();
        },

        onBoxQtyChanged() {
            this.fillQrPayloadFromSelection();
            this.fetchRecommendation();
        },

        zoneBadgeClass(priority) {
            if (Number(priority) === 1) return 'bg-zinc-950 text-white';
            if (Number(priority) === 2) return 'bg-orange-50 text-orange-900 border border-orange-300';
            return 'bg-emerald-50 text-emerald-900 border border-emerald-300';
        },

        buildQrLabelUrl() {
            return this.routes.qrLabel + '?branch_id=' + this.selectedBranchId + '&product_id=' + this.selectedProductId + '&boxes=' + this.receiveBoxes;
        },

        fillQrPayloadFromSelection() {
            const prod = this.activeProduct;
            if (!prod) return;
            this.qrPayloadInput = JSON.stringify({
                bulto: 'BLT-' + this.state.branch.code + '-' + prod.sku + '-01',
                folio: 'QR-' + new Date().toTimeString().slice(0, 5).replace(':', ''),
                destino: this.state.branch.code,
                sku: prod.sku,
                cajas: Number(this.receiveBoxes || 1),
                piezas: Number(this.receiveBoxes || 1) * Number(prod.master_factor)
            });
        },

        getRackPallet(level, pos) {
            return this.state.rack_grid[level + '-' + pos] || null;
        },

        openPalletModal(pallet) {
            this.modalPallet = pallet;
        },

        async apiRequest(url, method = 'GET', payload = null) {
            this.errorMessage = '';
            const opts = {
                method,
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                }
            };
            if (payload !== null) {
                opts.headers['Content-Type'] = 'application/json';
                opts.body = JSON.stringify(payload);
            }
            const res = await fetch(url, opts);
            const data = await res.json();
            if (!res.ok) {
                throw new Error(data.message || 'Verifica los datos ingresados.');
            }
            return data;
        },

        async loadBranchState(branchId) {
            this.busy = true;
            try {
                const data = await this.apiRequest(this.routes.stateBase + '/' + branchId + '/estado');
                this.state = data.state;
                this.branchesSummary = data.branchesSummary;
                this.fillQrPayloadFromSelection();
                await this.fetchRecommendation();
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.busy = false;
            }
        },

        async fetchRecommendation() {
            if (!this.selectedBranchId || !this.selectedProductId || this.receiveBoxes < 1) return;
            try {
                this.recommendation = await this.apiRequest(this.routes.recommend, 'POST', {
                    branch_id: Number(this.selectedBranchId),
                    product_id: Number(this.selectedProductId),
                    boxes: Number(this.receiveBoxes),
                    distance_band: this.distanceBand
                });
            } catch (err) {
                this.errorMessage = err.message;
            }
        },

        async commitReceive() {
            this.busy = true;
            try {
                const data = await this.apiRequest(this.routes.receive, 'POST', {
                    branch_id: Number(this.selectedBranchId),
                    product_id: Number(this.selectedProductId),
                    boxes: Number(this.receiveBoxes),
                    distance_band: this.distanceBand,
                    auto_slot_when_full: true
                });
                this.state = data.state;
                this.branchesSummary = data.branchesSummary;
                const summary = data.result.allocations.map(a =>
                    a.pallet_code + ' (' + a.boxes_placed + ' cajas' + (a.rack_assignment ? ' → ' + a.rack_assignment.coordinate : ' en piso') + ')'
                ).join(' + ');
                this.toastMessage = '✓ Entrada registrada (' + data.result.product_sku + '): ' + summary;
                await this.fetchRecommendation();
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.busy = false;
            }
        },

        async quickQueueArrival() {
            this.busy = true;
            try {
                const data = await this.apiRequest(this.routes.arrivalsStore, 'POST', {
                    branch_id: Number(this.selectedBranchId),
                    product_id: Number(this.selectedProductId),
                    boxes_expected: Number(this.receiveBoxes),
                    dock_code: 'R2',
                    eta_window: 'Programado hoy',
                    supplier_name: 'Proveedor Papelero CDMX'
                });
                this.state = data.state;
                this.branchesSummary = data.branchesSummary;
                this.toastMessage = '✓ Partida agregada a la cola de llegadas posteriores (' + data.arrival.product.sku + ' ×' + data.arrival.boxes_expected + ' cajas).';
                await this.fetchRecommendation();
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.busy = false;
            }
        },

        async saveNewProduct() {
            this.busy = true;
            try {
                const data = await this.apiRequest(this.routes.productsStore, 'POST', this.newProduct);
                this.products = data.products;
                this.selectedCategory = 'ALL';
                this.selectedProductId = data.product.id;
                this.showNewProductModal = false;
                this.toastMessage = '✓ Producto ' + data.product.sku + ' registrado en zona ' + data.product.stow_zone + '.';
                this.fillQrPayloadFromSelection();
                await this.fetchRecommendation();
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.busy = false;
            }
        },

        async receivePlannedLine(arr) {
            this.busy = true;
            try {
                const data = await this.apiRequest(this.routes.receive, 'POST', {
                    branch_id: Number(this.selectedBranchId),
                    product_id: Number(arr.product_id),
                    boxes: Number(arr.pending_boxes),
                    distance_band: this.distanceBand,
                    planned_arrival_id: Number(arr.id),
                    auto_slot_when_full: true
                });
                this.state = data.state;
                this.branchesSummary = data.branchesSummary;
                this.toastMessage = '✓ Llegada ' + arr.sku + ' (×' + arr.pending_boxes + ' cajas) ingresada a almacén.';
                await this.fetchRecommendation();
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.busy = false;
            }
        },

        async executeBatchSequence() {
            this.busy = true;
            try {
                const data = await this.apiRequest(this.routes.receiveBatch, 'POST', {
                    branch_id: Number(this.selectedBranchId),
                    distance_band: this.distanceBand
                });
                this.state = data.state;
                this.branchesSummary = data.branchesSummary;
                this.toastMessage = '✓ Lote completo acomodado en orden jerárquico (' + data.batch.total_boxes + ' cajas).';
                await this.fetchRecommendation();
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.busy = false;
            }
        },

        async slotOpenPallet(palletId) {
            this.busy = true;
            try {
                const data = await this.apiRequest(this.routes.slotBase + '/' + palletId + '/ubicar', 'POST', {});
                this.state = data.state;
                this.branchesSummary = data.branchesSummary;
                this.toastMessage = '✓ Tarima ' + data.assignment.pallet_code + ' ubicada en ' + data.assignment.coordinate;
                await this.fetchRecommendation();
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.busy = false;
            }
        },

        async startQrCamera() {
            this.qrCameraError = '';
            this.qrCameraState = 'loading';
            try {
                if (!this.qrInstance) {
                    this.qrInstance = new Html5Qrcode('wms-qr-reader');
                }
                await this.qrInstance.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 180, height: 180 } },
                    (decodedText) => {
                        this.qrPayloadInput = decodedText;
                        this.stopQrCamera();
                        this.submitQrScan();
                    },
                    () => {}
                );
                this.qrCameraState = 'scanning';
            } catch (err) {
                this.qrCameraState = 'idle';
                this.qrCameraError = 'No fue posible abrir la cámara. Usa el botón Procesar QR directamente.';
            }
        },

        async stopQrCamera() {
            if (this.qrInstance && this.qrCameraState === 'scanning') {
                try { await this.qrInstance.stop(); } catch (_) {}
            }
            this.qrCameraState = 'idle';
        },

        async submitQrScan() {
            this.busy = true;
            try {
                const data = await this.apiRequest(this.routes.qrScan, 'POST', {
                    qr_payload: this.qrPayloadInput,
                    distance_band: this.distanceBand
                });
                this.state = data.state;
                this.branchesSummary = data.branchesSummary;
                this.selectedBranchId = data.state.branch.id;
                this.showQrScannerModal = false;
                const summary = data.result.allocations.map(a =>
                    a.pallet_code + ' (' + a.boxes_placed + ' cajas' + (a.rack_assignment ? ' → ' + a.rack_assignment.coordinate : '') + ')'
                ).join(' + ');
                this.toastMessage = '✓ QR [' + data.scanned_bulto + '] ubicado en: ' + summary;
                await this.fetchRecommendation();
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.busy = false;
            }
        },

        async emitOutboundOrder() {
            this.busy = true;
            try {
                const data = await this.apiRequest(this.routes.outboundStore, 'POST', {
                    branch_id: Number(this.selectedBranchId),
                    dock_code: this.outboundForm.dock_code,
                    unit_plate: this.outboundForm.unit_plate,
                    driver_name: this.outboundForm.driver_name,
                    exception_authorized: Boolean(this.outboundForm.exception_authorized),
                    exception_reason: this.outboundForm.exception_reason
                });
                this.state = data.state;
                this.branchesSummary = data.branchesSummary;
                this.outboundForm.exception_authorized = false;
                this.outboundForm.exception_reason = '';
                this.toastMessage = '✓ Orden de Salida ' + data.order.folio + ' emitida (' + data.order.pallet_count + ' tarimas liberadas).';
                await this.fetchRecommendation();
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.busy = false;
            }
        },

        async resetDemo() {
            this.busy = true;
            try {
                const data = await this.apiRequest(this.routes.resetDemo, 'POST', {
                    branch_code: this.state.branch.code
                });
                this.state = data.state;
                this.branchesSummary = data.branchesSummary;
                this.toastMessage = data.message;
                await this.fetchRecommendation();
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.busy = false;
            }
        }
    };
}
</script>
@endsection
