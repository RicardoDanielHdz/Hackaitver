@extends('layouts.app')

@section('title', 'ERP de Embarques y Tráfico — Dokra')
@section('module-code', '02 · JEFE DE EMBARQUE Y TRÁFICO · CANDADO NORMATIVO ≥90%')

@section('content')
@php
    $readyCount = count(array_filter($branches, static fn (array $b): bool => $b['ready_for_torton']));
@endphp

<div x-data="{
        branches: @js($branches),
        csrfToken: @js(csrf_token()),
        emergenciesListUrl: @js(route('embarques.emergencies.index', [], false)),
        emergenciesStoreUrl: @js(route('embarques.emergencies.store', [], false)),
        emergencyRequests: @js($emergencyRequests ?? []),
        emergencyReason: '',
        sendingEmergency: false,
        emergencyBanner: '',
        emergencyError: '',
        selectedIndex: 0,
        branchSearch: '',
        statusFilter: 'TODAS',
        sortCriterion: 'FECHA_SLA',
        outboundDock: 'E1',
        unitPlate: '55-XA-2F',
        driverName: 'Roberto Ruiz Hernández',
        dispatching: false,
        showManifestModal: false,
        activeManifest: null,
        init() {
            setInterval(() => this.pollEmergencies(), 5000);
        },
        dispatchedList: [
            {
                folio: 'EMB-2026-041',
                seal: 'MCH-SUC-02-0041',
                branch: 'SUC-02 Polanco',
                rack: 'RACK-02',
                zone: 'Poniente (14 km)',
                dock: 'E2',
                plate: '71-VR-8B',
                driver: 'Miguel Ángel Torres',
                pallets: 12,
                weightKg: 11180,
                volM3: 26.22,
                weightPct: 93.2,
                volPct: 91.7,
                totalBoxes: 540,
                totalPieces: 8640,
                hasLiquids: false,
                hasHygroscopic: true,
                contents: [
                    { sku: 'PAP-006', name: 'Cartulina opalina blanca 225g paquete 100h', stow_zone: 'Base / Pesado', boxes: 240, pieces: 2400, weight_kg: 5024.0, is_liquid: false, supplier: 'Copamex Papelero S.A.' },
                    { sku: 'ESC-001', name: 'Lápiz grafito HB #2 caja 12 piezas', stow_zone: 'Intermedio / Estándar', boxes: 192, pieces: 4608, weight_kg: 3820.0, is_liquid: false, supplier: 'Azor México S.A.' },
                    { sku: 'ARC-008', name: 'Carpeta panorámica 1 pulgada carta', stow_zone: 'Superior / Ligero', boxes: 108, pieces: 1632, weight_kg: 2336.0, is_liquid: false, supplier: 'Oxford Archivo S.A.' }
                ],
                time: '09:45'
            },
            {
                folio: 'EMB-2026-040',
                seal: 'MCH-SUC-10-0040',
                branch: 'SUC-10 Tláhuac',
                rack: 'RACK-10',
                zone: 'Oriente (35 km)',
                dock: 'E1',
                plate: '55-XA-2F',
                driver: 'Roberto Ruiz Hernández',
                pallets: 12,
                weightKg: 11310,
                volM3: 26.48,
                weightPct: 94.3,
                volPct: 92.6,
                totalBoxes: 580,
                totalPieces: 9420,
                hasLiquids: true,
                hasHygroscopic: true,
                contents: [
                    { sku: 'PAP-007', name: 'Papel bond blanco carta 75g resma 500h', stow_zone: 'Base / Pesado', boxes: 240, pieces: 2400, weight_kg: 5688.0, is_liquid: false, supplier: 'Copamex Papelero S.A.' },
                    { sku: 'ADH-012', name: 'Pegamento líquido Resistol 850 1 kg', stow_zone: 'Base / Pesado', boxes: 80, pieces: 960, weight_kg: 1096.0, is_liquid: true, supplier: 'Henkel Adhesivos México' },
                    { sku: 'OFI-006', name: 'Grapas estándar 26/6 caja 5,000 pzas', stow_zone: 'Base / Pesado', boxes: 140, pieces: 3360, weight_kg: 2331.0, is_liquid: false, supplier: 'Distribuidora Metropolitana S.A.' },
                    { sku: 'ARC-011', name: 'Carpeta panorámica 3 argollas 2 pulgadas', stow_zone: 'Superior / Ligero', boxes: 120, pieces: 2700, weight_kg: 2195.0, is_liquid: false, supplier: 'Oxford Archivo S.A.' }
                ],
                time: '08:50'
            }
        ],
        get current() {
            return this.branches[this.selectedIndex];
        },
        get canDispatch() {
            return (this.current.weight_pct >= 90 && this.current.volume_pct >= 90);
        },
        get currentEmergencyRequest() {
            return this.emergencyRequests.find(r => r.branch_code === this.current.code && (r.status === 'pending' || r.status === 'approved')) || null;
        },
        async pollEmergencies() {
            try {
                const res = await fetch(this.emergenciesListUrl, { headers: { 'Accept': 'application/json' } });
                if (res.ok) {
                    const data = await res.json();
                    this.emergencyRequests = data.requests || [];
                }
            } catch (_) {}
        },
        async requestEmergencyDispatch() {
            this.sendingEmergency = true;
            this.emergencyError = '';
            this.emergencyBanner = '';
            try {
                const res = await fetch(this.emergenciesStoreUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken
                    },
                    body: JSON.stringify({
                        branch_code: this.current.code,
                        dock_code: this.outboundDock,
                        unit_plate: this.unitPlate,
                        driver_name: this.driverName,
                        emergency_reason: this.emergencyReason.trim() || ('Urgencia operativa de abasto en ' + this.current.code + ' ' + this.current.name),
                        estimated_pallets: Math.min(12, Math.max(1, Number(this.current.pallets || 6))),
                        estimated_weight_kg: Number(this.current.weight_kg || 6500),
                        weight_pct: Number(this.current.weight_pct || 54),
                        estimated_volume_m3: Number(this.current.volume_m3 || 15.5),
                        volume_pct: Number(this.current.volume_pct || 54)
                    })
                });
                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data.message || 'Verifica el motivo de la emergencia (mín. 5 caracteres).');
                }
                this.emergencyRequests = data.requests || [];
                this.emergencyBanner = data.message;
                this.emergencyReason = '';
            } catch (err) {
                this.emergencyError = err.message;
            } finally {
                this.sendingEmergency = false;
            }
        },
        get filteredBranches() {
            const q = this.branchSearch.trim().toLowerCase();
            let list = this.branches.map((b, idx) => ({ ...b, _idx: idx })).filter(b => {
                const matchSt = this.statusFilter === 'TODAS' ||
                    (this.statusFilter === 'LISTAS' && b.ready_for_torton) ||
                    (this.statusFilter === 'HOY' && b.sla_priority <= 2) ||
                    (this.statusFilter === 'BLOQUEADAS' && !b.ready_for_torton);
                const matchTxt = !q || b.code.toLowerCase().includes(q) || b.name.toLowerCase().includes(q) || b.rack_code.toLowerCase().includes(q);
                return matchSt && matchTxt;
            });

            if (this.sortCriterion === 'FECHA_SLA') {
                list.sort((a, b) => (a.sla_priority - b.sla_priority) || (b.weight_pct - a.weight_pct));
            } else {
                list.sort((a, b) => b.weight_pct - a.weight_pct);
            }
            return list;
        },
        openManifest(emb) {
            this.activeManifest = emb;
            this.showManifestModal = true;
        },
        dispatchTruck() {
            if (!this.canDispatch) return;
            this.dispatching = true;
            setTimeout(() => {
                const nextNum = 42 + this.dispatchedList.length;
                const newEntry = {
                    folio: 'EMB-2026-0' + nextNum,
                    seal: 'MCH-' + this.current.code + '-00' + nextNum,
                    branch: this.current.code + ' ' + this.current.name,
                    rack: this.current.rack_code,
                    zone: this.current.zone + ' (' + this.current.distance_km + ' km)',
                    dock: this.outboundDock,
                    plate: this.unitPlate,
                    driver: this.driverName,
                    pallets: Math.min(12, this.current.pallets),
                    weightKg: this.current.weight_kg,
                    volM3: this.current.volume_m3,
                    weightPct: this.current.weight_pct,
                    volPct: this.current.volume_pct,
                    totalBoxes: this.current.total_boxes || 0,
                    totalPieces: this.current.total_pieces || 0,
                    hasLiquids: Boolean(this.current.has_liquids),
                    hasHygroscopic: Boolean(this.current.has_hygroscopic),
                    contents: this.current.cargo_contents || [],
                    time: new Date().toTimeString().slice(0, 5)
                };
                this.dispatchedList.unshift(newEntry);
                this.activeManifest = newEntry;
                this.dispatching = false;
                this.showManifestModal = true;
            }, 300);
        }
     }"
     class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

    <!-- 1. Barra Superior Administrativa de Embarques -->
    <div class="bg-white border border-zinc-200 rounded-lg p-5 shadow-2xs">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-4 border-b border-zinc-200">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-2 py-0.5 text-[10px] font-mono font-bold uppercase tracking-widest bg-zinc-950 text-white rounded">
                        ERP EMBARQUES Y TRÁFICO · Dokra
                    </span>
                    <span class="px-2 py-0.5 text-[10px] font-mono font-bold bg-emerald-50 border border-emerald-300 text-emerald-800 rounded">
                        ● CANDADO DUAL ≥90% ACTIVO
                    </span>
                </div>
                <h1 class="text-xl sm:text-2xl font-extrabold text-zinc-950 tracking-tight mt-1">
                    Consola de Despacho Tortón, Andenes y Relaciones de Embarque
                </h1>
                <p class="text-xs text-zinc-500 mt-0.5">
                    Verificación obligatoria de capacidad (mínimo 10,800 kg y 25.73 m³ en 12 tarimas) antes de autorizar salida hacia los 32 destinos en CDMX.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                <a href="{{ route('wms.index') }}"
                   class="px-3.5 py-2 rounded-md border border-zinc-300 bg-white hover:border-zinc-950 text-zinc-800 text-xs font-bold uppercase tracking-wider transition flex items-center space-x-1.5">
                    <i class="fa-solid fa-cubes-stacked text-orange-500"></i>
                    <span>Órdenes de Salida WMS</span>
                </a>
                <button type="button"
                        @click="openManifest(dispatchedList[0])"
                        class="px-4 py-2 rounded-md bg-zinc-950 hover:bg-orange-600 text-white text-xs font-bold uppercase tracking-wider transition cursor-pointer flex items-center space-x-1.5">
                    <i class="fa-solid fa-print text-orange-400"></i>
                    <span>Última Relación QR</span>
                </button>
            </div>
        </div>

        <!-- 4 KPIs Administrativos -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 pt-4 font-mono tabular-nums text-xs">
            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block">Destinos Listos (&ge;90%)</span>
                    <strong class="text-base font-extrabold text-emerald-700">{{ $readyCount }} / 32 destinos</strong>
                </div>
                <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
            </div>

            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block">Andenes de Salida</span>
                    <strong class="text-base font-extrabold text-zinc-950">E1, E2, E3</strong>
                </div>
                <i class="fa-solid fa-warehouse text-zinc-400 text-base"></i>
            </div>

            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block">Salidas Emitidas Hoy</span>
                    <strong class="text-base font-extrabold text-zinc-950" x-text="dispatchedList.length + ' Tortones'"></strong>
                </div>
                <i class="fa-solid fa-truck-fast text-orange-500 text-base"></i>
            </div>

            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block">Impresora Caseta</span>
                    <strong class="text-base font-extrabold text-zinc-950">LÁSER ACTIVA</strong>
                </div>
                <i class="fa-solid fa-qrcode text-zinc-400 text-base"></i>
            </div>
        </div>
    </div>

    <!-- 2. Cuerpo Principal en 2 Columnas: Padrón de Destinos (5/12) + Mesa de Cubicaje y Despacho (7/12) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

        <!-- Izquierda (5/12): Tabla con Buscador y Filtros Rápidos -->
        <div class="lg:col-span-5 bg-white border border-zinc-200 rounded-lg overflow-hidden shadow-2xs flex flex-col">
            <div class="p-4 border-b border-zinc-200 bg-zinc-50/60 space-y-2.5">
                <div class="flex items-center justify-between">
                    <h2 class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                        Cola Priorizada por Fecha Compromiso y ≥90%
                    </h2>
                    <button type="button"
                            @click="sortCriterion = (sortCriterion === 'FECHA_SLA' ? 'OCUPACION' : 'FECHA_SLA')"
                            class="px-2 py-0.5 rounded bg-zinc-950 text-orange-400 font-mono text-[10px] font-bold cursor-pointer"
                            x-text="sortCriterion === 'FECHA_SLA' ? 'Orden: Fecha + ≥90%' : 'Orden: % Ocupación'"></button>
                </div>
                <div class="flex flex-wrap gap-1 font-mono text-[10px]">
                    <button type="button" @click="statusFilter = 'TODAS'"
                            :class="statusFilter === 'TODAS' ? 'bg-zinc-950 text-white' : 'bg-zinc-200 text-zinc-700'"
                            class="px-2 py-0.5 rounded font-bold cursor-pointer">Todas</button>
                    <button type="button" @click="statusFilter = 'HOY'"
                            :class="statusFilter === 'HOY' ? 'bg-zinc-950 text-white' : 'bg-zinc-200 text-zinc-700'"
                            class="px-2 py-0.5 rounded font-bold cursor-pointer">Entrega Hoy</button>
                    <button type="button" @click="statusFilter = 'LISTAS'"
                            :class="statusFilter === 'LISTAS' ? 'bg-zinc-950 text-white' : 'bg-zinc-200 text-zinc-700'"
                            class="px-2 py-0.5 rounded font-bold cursor-pointer">&ge;90%</button>
                    <button type="button" @click="statusFilter = 'BLOQUEADAS'"
                            :class="statusFilter === 'BLOQUEADAS' ? 'bg-zinc-950 text-white' : 'bg-zinc-200 text-zinc-700'"
                            class="px-2 py-0.5 rounded font-bold cursor-pointer">&lt;90%</button>
                </div>
                <input type="text" x-model="branchSearch" placeholder="Filtrar por código o destino CDMX..."
                       class="w-full bg-white border border-zinc-300 rounded px-3 py-1.5 text-xs focus:outline-none focus:border-orange-500">
            </div>

            <div class="overflow-y-auto max-h-[460px]">
                <table class="w-full text-left text-xs border-collapse font-mono tabular-nums">
                    <thead class="sticky top-0">
                        <tr class="bg-zinc-950 text-white text-[10px] uppercase">
                            <th class="py-2 px-3">Destino CDMX</th>
                            <th class="py-2 px-2">Fecha Compromiso</th>
                            <th class="py-2 px-3 text-right">Capacidad</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        <template x-for="b in filteredBranches" :key="b.code">
                            <tr @click="selectedIndex = b._idx"
                                class="cursor-pointer transition"
                                :class="selectedIndex === b._idx ? 'bg-orange-50 font-bold' : 'even:bg-zinc-50/80 hover:bg-zinc-100'">
                                <td class="py-2.5 px-3 font-sans">
                                    <span class="font-mono font-extrabold text-zinc-950" x-text="b.code"></span>
                                    <span class="text-zinc-800 block text-[11px] truncate max-w-[140px]" x-text="b.name"></span>
                                </td>
                                <td class="py-2.5 px-2 text-[10px]">
                                    <span class="font-bold"
                                          :class="b.sla_priority <= 2 ? 'text-orange-600' : 'text-zinc-700'"
                                          x-text="b.delivery_label"></span>
                                    <div class="text-zinc-500" x-text="b.rack_code + ' · ' + b.days_in_rack + 'd en rack'"></div>
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border"
                                          :class="b.ready_for_torton
                                            ? 'bg-emerald-50 border-emerald-300 text-emerald-800'
                                            : (b.sla_priority === 2 ? 'bg-amber-50 border-amber-300 text-amber-900' : 'bg-zinc-100 border-zinc-300 text-zinc-700')"
                                          x-text="b.ready_for_torton ? ('LISTA ' + b.weight_pct + '%') : (b.sla_priority === 2 ? ('HOY ' + b.weight_pct + '%') : (b.weight_pct + '%'))"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Derecha (7/12): Panel Administrativo de Cubicaje y Autorización de Salida -->
        <div class="lg:col-span-7 bg-white border border-zinc-200 rounded-lg p-5 flex flex-col justify-between space-y-4 shadow-2xs">
            <div class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-zinc-200">
                    <div>
                        <span class="text-[10px] font-mono font-bold uppercase tracking-widest text-orange-600">
                            AUDITORÍA DE CAPACIDAD · TORTÓN 12 TARIMAS
                        </span>
                        <h2 class="text-lg font-extrabold text-zinc-950" x-text="current.code + ' · ' + current.name"></h2>
                    </div>
                    <span class="font-mono text-xs font-bold px-2.5 py-1 rounded border self-start"
                          :class="canDispatch ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-zinc-100 text-zinc-700 border-zinc-300'"
                          x-text="canDispatch ? '● LIBERACIÓN AUTORIZADA (≥90%)' : '● SALIDA BLOQUEADA (<90%)'"></span>
                </div>

                <!-- Bloque 1: Datos de Viaje en Cuadrícula de 3 Columnas -->
                <div class="bg-zinc-50 border border-zinc-200 rounded-md p-3.5">
                    <div class="text-[10px] font-extrabold uppercase text-zinc-700 mb-2">1. Asignación de Andén y Unidad de Transporte</div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Andén de Salida</label>
                            <select x-model="outboundDock" class="w-full bg-white border border-zinc-300 rounded px-3 py-1.5 font-mono font-bold text-zinc-950">
                                <option value="E1">Andén E1</option>
                                <option value="E2">Andén E2</option>
                                <option value="E3">Andén E3</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Placas Tortón</label>
                            <input type="text" x-model="unitPlate" class="w-full bg-white border border-zinc-300 rounded px-3 py-1.5 font-mono text-zinc-950">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Operador Transporte</label>
                            <input type="text" x-model="driverName" class="w-full bg-white border border-zinc-300 rounded px-3 py-1.5 text-zinc-950">
                        </div>
                    </div>
                </div>

                <!-- Bloque 2: Doble Medidor de Capacidad -->
                <div class="bg-zinc-50 border border-zinc-200 rounded-md p-4 space-y-3 font-mono tabular-nums text-xs">
                    <div class="flex items-center justify-between text-[10px] font-bold uppercase text-zinc-600 border-b border-zinc-200 pb-1.5">
                        <span>2. Verificación Dual de Capacidad</span>
                        <span class="text-orange-600">Mínimo Obligatorio: 90.0%</span>
                    </div>

                    <div>
                        <div class="flex justify-between mb-1">
                            <span>PESO CARGADO: <strong class="text-zinc-950" x-text="current.weight_kg.toLocaleString('es-MX') + ' / 12,000 kg'"></strong></span>
                            <strong :class="current.weight_pct >= 90 ? 'text-emerald-700' : 'text-zinc-700'" x-text="current.weight_pct + '%'"></strong>
                        </div>
                        <div class="relative w-full h-3 bg-zinc-200 rounded-xs overflow-hidden">
                            <div class="h-full transition-all" :class="current.weight_pct >= 90 ? 'bg-orange-500' : 'bg-zinc-600'"
                                 :style="'width:' + Math.min(100, current.weight_pct) + '%'"></div>
                            <div class="absolute top-0 bottom-0 w-0.5 bg-zinc-950" style="left: 90%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between mb-1">
                            <span>VOLUMEN ENVOLVENTE: <strong class="text-zinc-950" x-text="current.volume_m3 + ' / 28.59 m³'"></strong></span>
                            <strong :class="current.volume_pct >= 90 ? 'text-emerald-700' : 'text-zinc-700'" x-text="current.volume_pct + '%'"></strong>
                        </div>
                        <div class="relative w-full h-3 bg-zinc-200 rounded-xs overflow-hidden">
                            <div class="h-full transition-all" :class="current.volume_pct >= 90 ? 'bg-orange-500' : 'bg-zinc-600'"
                                 :style="'width:' + Math.min(100, current.volume_pct) + '%'"></div>
                            <div class="absolute top-0 bottom-0 w-0.5 bg-zinc-950" style="left: 90%"></div>
                        </div>
                    </div>
                </div>

                <!-- Bloque 3: Grilla de 12 Posiciones del Tortón -->
                <div>
                    <div class="text-[10px] font-mono font-bold uppercase text-zinc-500 mb-2">
                        3. Plataforma Tortón (12 Posiciones de Tarima · 1.12 × 1.15 m)
                    </div>
                    <div class="grid grid-cols-6 gap-2 font-mono text-[10px] text-center">
                        <template x-for="slot in [1,2,3,4,5,6,7,8,9,10,11,12]" :key="slot">
                            <div class="py-2 px-1 rounded border"
                                 :class="slot <= Math.min(12, current.pallets) ? 'bg-zinc-950 text-white border-zinc-950' : 'bg-zinc-50 text-zinc-400 border-dashed border-zinc-300'">
                                <div class="font-bold" x-text="'T-' + String(slot).padStart(2, '0')"></div>
                                <div class="text-[9px]" :class="slot <= Math.min(12, current.pallets) ? 'text-orange-400' : 'text-zinc-400'"
                                     x-text="slot <= Math.min(12, current.pallets) ? 'CARGADA' : 'VACÍA'"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Pie de Acción o Aviso de Candado / Excepción por Fecha Compromiso -->
            <div class="pt-3 border-t border-zinc-200 space-y-2.5">
                <div class="flex items-center justify-between text-xs font-mono bg-zinc-50 border border-zinc-200 rounded px-3 py-2">
                    <span>FECHA COMPROMISO DE ENTREGA: <strong class="text-zinc-950" x-text="current.delivery_label"></strong></span>
                    <span class="text-zinc-500" x-text="'Antigüedad FIFO en rack: ' + current.days_in_rack + ' día(s)'"></span>
                </div>

                <!-- Sección de Salida Forzada por Caso de Emergencia (<90%) -->
                <div x-show="!current.ready_for_torton" x-cloak class="p-3.5 rounded-md bg-red-50/70 border-2 border-red-300 text-zinc-900 text-xs space-y-2.5">
                    <div class="font-mono">
                        <strong class="text-red-800">⚠ CANDADO NORMATIVO ACTIVO (&lt;90%):</strong> Faltan
                        <strong class="text-red-700" x-text="current.missing_weight_kg.toLocaleString('es-MX') + ' kg'"></strong> y
                        <strong class="text-red-700" x-text="current.missing_volume_m3 + ' m³'"></strong>
                        para llenar el Tortón al 90%.
                    </div>

                    <div x-show="emergencyBanner !== ''" x-cloak class="p-2.5 rounded bg-zinc-950 text-white font-mono text-[11px]">
                        <span class="text-orange-400 font-bold">✓ ENVIADA:</span> <span x-text="emergencyBanner"></span>
                    </div>
                    <div x-show="emergencyError !== ''" x-cloak class="p-2 rounded bg-red-900 text-white text-[11px]" x-text="emergencyError"></div>

                    <!-- Si ya fue aprobada por el Gerente, mostrar botón directo al PDF / Etiqueta Maestra -->
                    <template x-if="currentEmergencyRequest && currentEmergencyRequest.status === 'approved' && currentEmergencyRequest.manifest_url">
                        <div class="p-3 rounded bg-emerald-950 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <span class="font-mono text-[10px] font-bold uppercase text-emerald-400 block">
                                    ✓ EMERGENCIA APROBADA POR GERENCIA (<span x-text="currentEmergencyRequest.reviewed_by || 'Gerente'"></span>)
                                </span>
                                <strong class="font-mono text-xs" x-text="'Orden ' + currentEmergencyRequest.outbound_folio + ' liberada para salida inmediata'"></strong>
                            </div>
                            <a :href="currentEmergencyRequest.manifest_url" target="_blank"
                               class="px-3.5 py-2 rounded bg-orange-500 hover:bg-orange-600 text-white font-mono text-[11px] font-bold uppercase text-center shrink-0">
                                <i class="fa-solid fa-file-pdf mr-1"></i> Imprimir PDF y Etiqueta Salida ↗
                            </a>
                        </div>
                    </template>

                    <!-- Si está pendiente de aprobación del Gerente -->
                    <template x-if="currentEmergencyRequest && currentEmergencyRequest.status === 'pending'">
                        <div class="p-2.5 rounded bg-amber-100 border border-amber-400 text-amber-950 font-mono text-[11px] flex items-center justify-between">
                            <span>
                                <strong>● EN ESPERA DE GERENCIA:</strong> Solicitud <strong x-text="currentEmergencyRequest.folio"></strong> enviada al Gerente General.
                            </span>
                            <span class="text-[10px] uppercase font-bold">Pendiente</span>
                        </div>
                    </template>

                    <!-- Formulario para forzar salida por emergencia -->
                    <div class="pt-2 border-t border-red-200 space-y-2">
                        <div class="font-mono text-[10px] font-extrabold uppercase text-red-800">
                            Caso de Emergencia: Forzar Salida de Tortón Incompleto (Envía Notificación Urgente al Gerente)
                        </div>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <input type="text"
                                   x-model="emergencyReason"
                                   placeholder="Escribe el motivo urgente (ej. Desabasto crítico en sucursal / pedido escolar urgente)..."
                                   class="flex-1 bg-white border border-red-300 rounded px-3 py-2 text-xs text-zinc-950 focus:outline-none focus:border-red-600">
                            <button type="button"
                                    @click="requestEmergencyDispatch()"
                                    :disabled="sendingEmergency"
                                    class="px-4 py-2 rounded bg-red-700 hover:bg-red-800 disabled:bg-zinc-300 text-white font-mono text-[11px] font-bold uppercase tracking-wider cursor-pointer shrink-0">
                                <i class="fa-solid fa-bell mr-1"></i>
                                <span x-show="!sendingEmergency">Forzar Salida de Emergencia</span>
                                <span x-show="sendingEmergency" x-cloak>Notificando...</span>
                            </button>
                        </div>
                    </div>
                </div>

                <button x-show="canDispatch"
                        type="button"
                        @click="dispatchTruck"
                        :disabled="dispatching"
                        class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold text-xs uppercase tracking-wider py-3 px-5 rounded-md transition cursor-pointer flex items-center justify-center space-x-2">
                    <i class="fa-solid fa-file-pdf"></i>
                    <span x-show="!dispatching">Autorizar Despacho Tortón y Emitir Relación de Embarque QR</span>
                    <span x-show="dispatching" x-cloak>Emitiendo manifiesto...</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Bandeja en Vivo de Solicitudes de Emergencia (<90%) y Documentos PDF Autorizados -->
    <div x-show="emergencyRequests.length > 0" x-cloak
         class="bg-white border-2 border-zinc-950 rounded-lg overflow-hidden shadow-2xs">
        <div class="bg-zinc-950 text-white px-5 py-3 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center space-x-2">
                <span class="px-2 py-0.5 rounded bg-red-600 text-white font-mono text-[10px] font-extrabold uppercase">
                    CASOS DE EMERGENCIA (&lt;90%)
                </span>
                <h3 class="text-xs font-extrabold uppercase tracking-wider">
                    Seguimiento de Salidas de Emergencia y Documentos PDF Autorizados por Gerencia (<span x-text="emergencyRequests.length"></span>)
                </h3>
            </div>
            <span class="font-mono text-[10px] text-zinc-400">Sincronización automática con Gerencia General</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse font-mono tabular-nums">
                <thead>
                    <tr class="bg-zinc-100 border-b border-zinc-300 text-[10px] uppercase text-zinc-700">
                        <th class="py-2.5 px-4">Folio Emergencia</th>
                        <th class="py-2.5 px-3">Sucursal / Carga</th>
                        <th class="py-2.5 px-3">Andén / Unidad</th>
                        <th class="py-2.5 px-3">Motivo Urgencia</th>
                        <th class="py-2.5 px-3">Dictamen Gerente</th>
                        <th class="py-2.5 px-4 text-right">PDF y Etiqueta Salida</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    <template x-for="req in emergencyRequests" :key="req.id">
                        <tr class="even:bg-zinc-50/80">
                            <td class="py-2.5 px-4 font-extrabold text-zinc-950">
                                <span x-text="req.folio"></span>
                                <span class="block text-[10px] font-normal text-zinc-500" x-text="req.created_at"></span>
                            </td>
                            <td class="py-2.5 px-3 font-sans">
                                <strong class="font-mono text-zinc-950" x-text="req.branch_code + ' · ' + req.branch_name"></strong>
                                <span class="block font-mono text-[10px] text-zinc-500"
                                      x-text="req.estimated_pallets + '/12 tar · ' + req.weight_pct + '% P · ' + req.volume_pct + '% V'"></span>
                            </td>
                            <td class="py-2.5 px-3" x-text="req.dock_code + ' · ' + req.unit_plate"></td>
                            <td class="py-2.5 px-3 font-sans text-zinc-700 max-w-xs">
                                <div class="truncate" x-text="req.emergency_reason"></div>
                                <div x-show="req.manager_notes" class="text-[10px] text-zinc-500 truncate" x-text="'Nota: ' + (req.manager_notes || '')"></div>
                            </td>
                            <td class="py-2.5 px-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold border"
                                      :class="req.status === 'approved'
                                        ? 'bg-emerald-50 border-emerald-300 text-emerald-800'
                                        : (req.status === 'rejected' ? 'bg-red-50 border-red-300 text-red-800' : 'bg-amber-50 border-amber-300 text-amber-900')"
                                      x-text="req.status === 'approved' ? ('✓ AUTORIZADA (' + (req.outbound_folio || '') + ')') : (req.status === 'rejected' ? '✕ RECHAZADA' : '● PENDIENTE GERENTE')"></span>
                            </td>
                            <td class="py-2.5 px-4 text-right whitespace-nowrap">
                                <template x-if="req.status === 'approved' && req.manifest_url">
                                    <a :href="req.manifest_url" target="_blank"
                                       class="inline-block px-3 py-1.5 rounded bg-orange-500 hover:bg-orange-600 text-white text-[10px] font-bold uppercase">
                                        <i class="fa-solid fa-file-pdf mr-1"></i> Descargar / Imprimir PDF ↗
                                    </a>
                                </template>
                                <template x-if="req.status === 'pending'">
                                    <span class="text-[10px] text-amber-800 font-bold uppercase">Esperando firma...</span>
                                </template>
                                <template x-if="req.status === 'rejected'">
                                    <span class="text-[10px] text-zinc-400 uppercase">Sin salida</span>
                                </template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Padrón de Entregas Programadas y Proveedores Activos (Exclusivo Gerente y Jefe de Embarque) -->
    @include('roles.partials.padron-entregas-programadas')

    <!-- 3. Tabla Principal Cebrada de Relaciones de Embarque Emitidas Hoy -->
    <div class="bg-white border border-zinc-200 rounded-lg overflow-hidden shadow-2xs">
        <div class="px-5 py-3.5 border-b border-zinc-200 bg-zinc-50/60 flex items-center justify-between">
            <div>
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                    Padrón de Relaciones de Embarque Emitidas Hoy
                </h3>
                <p class="text-[11px] text-zinc-500">Haz clic en <strong>Ver Ficha / QR</strong> para abrir e imprimir el manifiesto oficial del viaje.</p>
            </div>
            <span class="font-mono text-xs text-zinc-600" x-text="dispatchedList.length + ' salidas registradas'"></span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse font-mono tabular-nums">
                <thead>
                    <tr class="bg-zinc-950 text-white text-[10px] uppercase tracking-wider">
                        <th class="py-3 px-4">Folio Embarque</th>
                        <th class="py-3 px-3">Hora Salida</th>
                        <th class="py-3 px-3">Sucursal Destino (CDMX)</th>
                        <th class="py-3 px-3">Andén / Unidad</th>
                        <th class="py-3 px-3">Tarimas</th>
                        <th class="py-3 px-3">Ocupación</th>
                        <th class="py-3 px-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    <template x-for="emb in dispatchedList" :key="emb.folio">
                        <tr class="even:bg-zinc-50/80 hover:bg-orange-50/40 transition">
                            <td class="py-3 px-4 font-extrabold text-zinc-950" x-text="emb.folio"></td>
                            <td class="py-3 px-3 text-zinc-600" x-text="emb.time"></td>
                            <td class="py-3 px-3 font-sans font-bold text-zinc-900" x-text="emb.branch"></td>
                            <td class="py-3 px-3 text-zinc-700" x-text="emb.dock + ' · ' + (emb.plate || unitPlate)"></td>
                            <td class="py-3 px-3 text-zinc-800" x-text="emb.pallets + ' / 12'"></td>
                            <td class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 border border-emerald-300 text-emerald-800"
                                      x-text="emb.weightPct + '% P · ' + emb.volPct + '% V'"></span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <button type="button"
                                        @click="openManifest(emb)"
                                        class="px-2.5 py-1 rounded border border-zinc-300 bg-white hover:border-zinc-950 text-zinc-800 text-[10px] font-bold uppercase cursor-pointer">
                                    Ver Ficha / QR
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Imprimible: Etiqueta Maestra y Relación de Embarque Completa con Desglose de Contenido -->
    <div x-show="showManifestModal" x-cloak class="fixed inset-0 z-50 bg-zinc-950/75 flex items-center justify-center p-4">
        <template x-if="activeManifest">
            <div @click.outside="showManifestModal = false"
                 class="bg-white text-zinc-950 border-2 border-zinc-950 rounded-lg max-w-3xl w-full max-h-[90vh] flex flex-col overflow-hidden shadow-2xl">
                <div class="bg-zinc-950 text-white px-6 py-4 flex items-center justify-between shrink-0">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-2 py-0.5 rounded bg-orange-500 text-zinc-950 font-mono text-[10px] font-extrabold uppercase">
                                ETIQUETA MAESTRA DE EMBARQUE FINAL TORTÓN · Dokra
                            </span>
                            <span class="font-mono text-[11px] text-zinc-300" x-text="'MARCHAMO: ' + (activeManifest.seal || 'MCH-2026')"></span>
                        </div>
                        <h3 class="text-lg font-extrabold text-white mt-1"
                            x-text="activeManifest.folio + ' · ' + activeManifest.branch"></h3>
                    </div>
                    <button type="button" @click="showManifestModal = false" class="text-zinc-400 hover:text-white cursor-pointer">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs overflow-y-auto">
                    <!-- Bloque 1: Origen, Destino, Unidad y QR Maestro -->
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-center bg-zinc-50 border border-zinc-200 rounded-md p-4 font-mono">
                        <div class="sm:col-span-9 space-y-2">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <div class="bg-white border border-zinc-300 rounded p-2.5">
                                    <span class="text-[9px] text-zinc-500 uppercase block">ORIGEN / SALIDA</span>
                                    <strong class="font-sans text-zinc-950">Dokra · Andén <span x-text="activeManifest.dock"></span></strong>
                                    <div class="text-[10px] text-zinc-600" x-text="'Rack liberado: ' + (activeManifest.rack || 'RACK-01') + ' · Salida ' + activeManifest.time"></div>
                                </div>
                                <div class="bg-white border-2 border-zinc-950 rounded p-2.5">
                                    <span class="text-[9px] text-orange-600 font-bold uppercase block">DESTINO FINAL</span>
                                    <strong class="font-sans text-zinc-950" x-text="activeManifest.branch"></strong>
                                    <div class="text-[10px] text-zinc-600" x-text="(activeManifest.zone || 'CDMX') + ' · Placas ' + activeManifest.plate"></div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center bg-white border border-zinc-200 rounded p-2">
                                <div>
                                    <span class="text-[9px] text-zinc-500 block">TARIMAS</span>
                                    <strong class="text-zinc-950" x-text="activeManifest.pallets + ' / 12'"></strong>
                                </div>
                                <div>
                                    <span class="text-[9px] text-zinc-500 block">CAJAS MÁSTER</span>
                                    <strong class="text-zinc-950" x-text="Number(activeManifest.totalBoxes || 0).toLocaleString('es-MX')"></strong>
                                </div>
                                <div>
                                    <span class="text-[9px] text-zinc-500 block">PIEZAS TOTALES</span>
                                    <strong class="text-zinc-950" x-text="Number(activeManifest.totalPieces || 0).toLocaleString('es-MX')"></strong>
                                </div>
                                <div>
                                    <span class="text-[9px] text-zinc-500 block">CAPACIDAD</span>
                                    <strong class="text-emerald-700" x-text="activeManifest.weightPct + '% P · ' + activeManifest.volPct + '% V'"></strong>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-2 text-[10px]">
                                <span><strong>OPERADOR:</strong> <span class="font-sans" x-text="activeManifest.driver"></span></span>
                                <div class="flex flex-wrap gap-1">
                                    <template x-if="activeManifest.hasLiquids">
                                        <span class="px-1.5 py-0.5 rounded bg-red-600 text-white font-bold">⚠ CONTIENE LÍQUIDOS</span>
                                    </template>
                                    <template x-if="activeManifest.hasHygroscopic">
                                        <span class="px-1.5 py-0.5 rounded bg-zinc-950 text-white font-bold">PAPEL · MANTENER SECO</span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="sm:col-span-3 border-2 border-zinc-950 bg-white p-3 text-center">
                            <i class="fa-solid fa-qrcode text-6xl text-zinc-950"></i>
                            <div class="text-[9px] font-bold mt-1.5 uppercase">QR MAESTRO CONTENIDO</div>
                            <div class="text-[9px] text-zinc-500" x-text="activeManifest.folio"></div>
                        </div>
                    </div>

                    <!-- Bloque 2: Desglose de Contenido Embarcado -->
                    <div class="border border-zinc-300 rounded overflow-hidden">
                        <div class="bg-zinc-950 text-white px-3.5 py-2 flex items-center justify-between font-mono text-[10px] uppercase">
                            <span>Contenido Consolidado dentro de la Unidad Tortón</span>
                            <span class="text-orange-400" x-text="(activeManifest.contents || []).length + ' SKUs · ' + Number(activeManifest.weightKg || 0).toLocaleString('es-MX') + ' kg · ' + (activeManifest.volM3 || 0) + ' m³'"></span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse font-mono tabular-nums">
                                <thead>
                                    <tr class="bg-zinc-100 border-b border-zinc-300 text-[10px] uppercase text-zinc-700">
                                        <th class="py-2 px-3">SKU</th>
                                        <th class="py-2 px-3">Artículo / Proveedor Origen</th>
                                        <th class="py-2 px-2">Zona Estiba</th>
                                        <th class="py-2 px-2 text-right">Cajas</th>
                                        <th class="py-2 px-2 text-right">Piezas</th>
                                        <th class="py-2 px-3 text-right">Peso</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200">
                                    <template x-for="item in (activeManifest.contents || [])" :key="item.sku">
                                        <tr class="even:bg-zinc-50/80">
                                            <td class="py-2 px-3 font-extrabold text-zinc-950 whitespace-nowrap" x-text="item.sku"></td>
                                            <td class="py-2 px-3 font-sans">
                                                <div class="font-bold text-zinc-900" x-text="item.name"></div>
                                                <div class="text-[10px] text-zinc-500" x-text="'Origen: ' + (item.supplier || 'Proveedor CDMX')"></div>
                                            </td>
                                            <td class="py-2 px-2 text-[10px]">
                                                <span x-text="item.stow_zone"></span>
                                                <template x-if="item.is_liquid">
                                                    <span class="block text-red-700 font-bold">LÍQUIDO</span>
                                                </template>
                                            </td>
                                            <td class="py-2 px-2 text-right font-bold text-zinc-950" x-text="Number(item.boxes).toLocaleString('es-MX')"></td>
                                            <td class="py-2 px-2 text-right text-zinc-700" x-text="Number(item.pieces).toLocaleString('es-MX')"></td>
                                            <td class="py-2 px-3 text-right font-bold" x-text="Number(item.weight_kg).toLocaleString('es-MX') + ' kg'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Bloque 3: Distribución de Ejes y Acuse -->
                    <div class="grid grid-cols-3 gap-2 font-mono text-[10px] text-center bg-zinc-50 border border-zinc-200 rounded p-2.5">
                        <div class="bg-white border border-zinc-200 rounded p-1.5">
                            <span class="text-zinc-500 block">EJE DELANTERO</span>
                            <strong class="text-zinc-950">T-01 a T-04 (Carga Base)</strong>
                        </div>
                        <div class="bg-white border border-zinc-200 rounded p-1.5">
                            <span class="text-zinc-500 block">EJE CENTRAL</span>
                            <strong class="text-zinc-950">T-05 a T-08 (Intermedio)</strong>
                        </div>
                        <div class="bg-white border border-zinc-200 rounded p-1.5">
                            <span class="text-zinc-500 block">EJE TRASERO</span>
                            <strong class="text-zinc-950">T-09 a T-12 (Descarga 1ª)</strong>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-3 bg-zinc-100 border-t border-zinc-200 flex justify-end space-x-2 shrink-0">
                    <button type="button" @click="showManifestModal = false"
                            class="px-4 py-1.5 rounded border border-zinc-300 bg-white text-xs font-bold uppercase text-zinc-700 cursor-pointer">
                        Cerrar
                    </button>
                    <button type="button" onclick="window.print()"
                            class="px-4 py-1.5 rounded bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold uppercase cursor-pointer">
                        <i class="fa-solid fa-print mr-1"></i> Imprimir Etiqueta y Manifiesto PDF
                    </button>
                </div>
            </div>
        </template>
    </div>

</div>
@endsection
