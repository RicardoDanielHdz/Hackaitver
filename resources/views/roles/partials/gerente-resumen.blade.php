<div x-data="{
        branchSearch: '',
        readinessFilter: 'TODAS',
        selectedBranchDetail: null,
        exceptionApproved: false,
        get filteredBranches() {
            const q = this.branchSearch.trim().toLowerCase();
            return this.branches.filter((b, idx) => {
                b._idx = idx;
                const matchStatus = this.readinessFilter === 'TODAS' ||
                    (this.readinessFilter === 'LISTAS' && b.ready_for_torton) ||
                    (this.readinessFilter === 'ACUMULACION' && !b.ready_for_torton);
                const matchText = !q ||
                    b.code.toLowerCase().includes(q) ||
                    b.name.toLowerCase().includes(q) ||
                    b.zone.toLowerCase().includes(q) ||
                    b.rack_code.toLowerCase().includes(q);
                return matchStatus && matchText;
            });
        }
     }"
     class="space-y-5">

    <!-- 1. Barra de Búsqueda y Filtros Rápidos para los 32 Destinos -->
    <div class="bg-white border border-zinc-200 rounded-lg p-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="relative flex-1 max-w-md">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-zinc-400 text-xs"></i>
            <input type="text"
                   x-model="branchSearch"
                   placeholder="Buscar sucursal destino por código (SUC-01), nombre, zona o rack..."
                   class="w-full bg-zinc-50 border border-zinc-300 rounded-md pl-8 pr-16 py-1.5 text-xs text-zinc-950 focus:bg-white focus:outline-none focus:border-orange-500">
            <button x-show="branchSearch !== ''" type="button" @click="branchSearch = ''"
                    class="absolute right-2.5 top-1.5 text-[11px] font-mono text-zinc-500 hover:text-zinc-950 cursor-pointer">
                Limpiar
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-2 font-mono text-xs">
            <span class="text-[10px] font-bold uppercase text-zinc-500">Estatus Tortón:</span>
            <button type="button" @click="readinessFilter = 'TODAS'"
                    :class="readinessFilter === 'TODAS' ? 'bg-zinc-950 text-white' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200'"
                    class="px-2.5 py-1 rounded text-[11px] font-bold cursor-pointer">
                Todas (32)
            </button>
            <button type="button" @click="readinessFilter = 'LISTAS'"
                    :class="readinessFilter === 'LISTAS' ? 'bg-zinc-950 text-white' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200'"
                    class="px-2.5 py-1 rounded text-[11px] font-bold cursor-pointer">
                Listas &ge;90% ({{ $readyCount }})
            </button>
            <button type="button" @click="readinessFilter = 'ACUMULACION'"
                    :class="readinessFilter === 'ACUMULACION' ? 'bg-zinc-950 text-white' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200'"
                    class="px-2.5 py-1 rounded text-[11px] font-bold cursor-pointer">
                En Acumulación ({{ 32 - $readyCount }})
            </button>
        </div>
    </div>

    <!-- 2. Tabla Principal Cebrada de los 32 Destinos CDMX -->
    <div class="bg-white border border-zinc-200 rounded-lg overflow-hidden shadow-2xs">
        <div class="px-5 py-3.5 border-b border-zinc-200 bg-zinc-50/60 flex items-center justify-between">
            <div>
                <h2 class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                    Padrón de Consolidación · 32 Sucursales Destino CDMX
                </h2>
                <p class="text-[11px] text-zinc-500">
                    Consulta el avance de peso y volumen por destino. Haz clic en <strong>Ver Ficha</strong> o <strong>Ver Rack</strong> para inspeccionar detalles.
                </p>
            </div>
            <span class="font-mono text-xs text-zinc-600" x-text="filteredBranches.length + ' destinos mostrados'"></span>
        </div>

        <div class="overflow-x-auto max-h-[440px] overflow-y-auto">
            <table class="w-full text-left text-xs border-collapse font-mono tabular-nums">
                <thead class="sticky top-0 z-10">
                    <tr class="bg-zinc-950 text-white text-[10px] uppercase tracking-wider">
                        <th class="py-3 px-4">Código / Destino CDMX</th>
                        <th class="py-3 px-3">Zona / Distancia</th>
                        <th class="py-3 px-3">Rack Asignado</th>
                        <th class="py-3 px-3">Peso Tortón (12,000 kg)</th>
                        <th class="py-3 px-3">Volumen (28.59 m³)</th>
                        <th class="py-3 px-3">Estatus</th>
                        <th class="py-3 px-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    <template x-for="b in filteredBranches" :key="b.code">
                        <tr class="even:bg-zinc-50/80 hover:bg-orange-50/40 transition">
                            <td class="py-2.5 px-4 font-sans">
                                <span class="font-mono font-extrabold text-zinc-950" x-text="b.code"></span>
                                <span class="font-bold text-zinc-900 ml-1" x-text="b.name"></span>
                            </td>
                            <td class="py-2.5 px-3 font-sans text-zinc-600">
                                <span x-text="b.zone"></span>
                                <span class="font-mono text-[11px] text-zinc-400 ml-1" x-text="'(' + b.distance_km + ' km)'"></span>
                            </td>
                            <td class="py-2.5 px-3">
                                <strong class="text-zinc-950" x-text="b.rack_code"></strong>
                                <span class="text-zinc-500 ml-1" x-text="'(' + b.pallets + '/32 tar)'"></span>
                            </td>
                            <td class="py-2.5 px-3">
                                <div class="flex items-center justify-between mb-1">
                                    <strong class="text-zinc-950" x-text="b.weight_pct + '%'"></strong>
                                    <span class="text-[10px] text-zinc-500" x-text="Number(b.weight_kg).toLocaleString('es-MX') + ' kg'"></span>
                                </div>
                                <div class="w-28 h-1.5 bg-zinc-200 rounded-xs overflow-hidden">
                                    <div class="h-full" :class="b.ready_for_torton ? 'bg-orange-500' : 'bg-zinc-700'"
                                         :style="'width:' + Math.min(100, b.weight_pct) + '%'"></div>
                                </div>
                            </td>
                            <td class="py-2.5 px-3">
                                <strong class="text-zinc-950" x-text="b.volume_pct + '%'"></strong>
                                <span class="block text-[10px] text-zinc-500" x-text="b.volume_m3 + ' m³'"></span>
                            </td>
                            <td class="py-2.5 px-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold border"
                                      :class="b.ready_for_torton
                                        ? 'bg-emerald-50 border-emerald-300 text-emerald-800'
                                        : 'bg-zinc-100 border-zinc-300 text-zinc-700'"
                                      x-text="b.ready_for_torton ? '● LISTA ≥90%' : 'ACUMULANDO'"></span>
                            </td>
                            <td class="py-2.5 px-4 text-right whitespace-nowrap space-x-1">
                                <button type="button"
                                        @click="selectedBranchDetail = b"
                                        class="px-2.5 py-1 rounded border border-zinc-300 bg-white hover:border-zinc-950 text-zinc-800 text-[10px] font-bold uppercase cursor-pointer">
                                    Ver Ficha
                                </button>
                                <button type="button"
                                        @click="selectedBranchIndex = b._idx; tab = 'racks'"
                                        class="px-2.5 py-1 rounded bg-zinc-950 hover:bg-orange-500 text-white text-[10px] font-bold uppercase cursor-pointer">
                                    Ver Rack
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. Tablas de Andenes en Vivo y Bitácora Auditable de Incidencias -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <!-- Tabla de los 7 Andenes (5/12) -->
        <div class="lg:col-span-5 bg-white border border-zinc-200 rounded-lg overflow-hidden shadow-2xs">
            <div class="px-5 py-3.5 border-b border-zinc-200 bg-zinc-50/60">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                    Monitor de Andenes (4 Recibo · 3 Embarque)
                </h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse font-mono tabular-nums">
                    <thead>
                        <tr class="bg-zinc-950 text-white text-[10px] uppercase">
                            <th class="py-2 px-3">Andén</th>
                            <th class="py-2 px-3">Proveedor / Destino</th>
                            <th class="py-2 px-3">Horario / Avance</th>
                            <th class="py-2 px-3 text-right">Estatus</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @foreach($docks['inbound'] as $dock)
                            <tr class="even:bg-zinc-50/80">
                                <td class="py-2.5 px-3 font-bold text-zinc-950">{{ $dock['code'] }}</td>
                                <td class="py-2.5 px-3 font-sans">
                                    <div class="font-bold text-zinc-900">{{ $dock['supplier'] }}</div>
                                    <div class="font-mono text-[10px] text-zinc-500">{{ $dock['oc'] }}</div>
                                </td>
                                <td class="py-2.5 px-3 text-[11px] text-zinc-600">
                                    {{ $dock['slot'] }}
                                    <span class="block text-[10px] text-zinc-500">{{ $dock['progress'] }}</span>
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border {{ $dock['status'] === 'EN OPERACIÓN' ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : 'bg-zinc-100 border-zinc-300 text-zinc-700' }}">
                                        {{ $dock['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                        @foreach($docks['outbound'] as $dock)
                            <tr class="even:bg-zinc-50/80">
                                <td class="py-2.5 px-3 font-bold text-orange-600">{{ $dock['code'] }}</td>
                                <td class="py-2.5 px-3 font-sans">
                                    <div class="font-bold text-zinc-900">{{ $dock['branch'] }}</div>
                                    <div class="font-mono text-[10px] text-zinc-500">{{ $dock['unit'] }}</div>
                                </td>
                                <td class="py-2.5 px-3 text-[11px] text-zinc-600">{{ $dock['fill'] }}</td>
                                <td class="py-2.5 px-3 text-right">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border {{ $dock['status'] === 'CARGANDO' ? 'bg-orange-50 border-orange-300 text-orange-900' : 'bg-zinc-100 border-zinc-300 text-zinc-700' }}">
                                        {{ $dock['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bitácora Auditable de Incidencias (7/12) -->
        <div class="lg:col-span-7 bg-white border border-zinc-200 rounded-lg overflow-hidden shadow-2xs flex flex-col justify-between">
            <div>
                <div class="px-5 py-3.5 border-b border-zinc-200 bg-zinc-50/60 flex items-center justify-between">
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                        Bitácora de Rechazos QR y Autorizaciones Gerenciales
                    </h3>
                    <span class="text-[10px] font-mono uppercase px-2 py-0.5 rounded bg-zinc-100 text-zinc-700 border border-zinc-300">
                        Registro Auditable
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse font-mono tabular-nums">
                        <thead>
                            <tr class="bg-zinc-950 text-white text-[10px] uppercase">
                                <th class="py-2 px-3">Hora / Folio</th>
                                <th class="py-2 px-3">Tipo Incidencia</th>
                                <th class="py-2 px-3">Detalle Técnico</th>
                                <th class="py-2 px-3">Operador</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            @foreach($incidents as $inc)
                                <tr class="even:bg-zinc-50/80 hover:bg-orange-50/40">
                                    <td class="py-2.5 px-3 whitespace-nowrap">
                                        <strong class="text-zinc-950">{{ $inc['time'] }}</strong>
                                        <span class="block text-[10px] text-zinc-500">{{ $inc['oc'] }} · {{ $inc['package'] }}</span>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 border border-amber-300 text-amber-900">
                                            {{ $inc['type'] }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 font-sans text-zinc-700">{{ $inc['detail'] }}</td>
                                    <td class="py-2.5 px-3 font-sans text-zinc-600 whitespace-nowrap">{{ $inc['operator'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="m-4 p-3.5 rounded-md bg-zinc-950 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="text-xs">
                    <div class="font-mono text-[10px] uppercase tracking-wider text-orange-400 font-bold">Control de Excepciones (&lt;90%)</div>
                    <p class="text-zinc-300 mt-0.5">
                        Ningún Tortón sale debajo del 90% sin autorización firmada por Gerencia General.
                    </p>
                </div>
                <button type="button"
                        @click="exceptionApproved = !exceptionApproved"
                        class="shrink-0 px-3 py-2 rounded text-[11px] font-mono font-bold uppercase border transition cursor-pointer"
                        :class="exceptionApproved ? 'bg-orange-500 border-orange-500 text-white' : 'bg-zinc-900 border-zinc-700 text-zinc-200 hover:border-orange-500'">
                    <span x-show="!exceptionApproved">Candado 90% Estricto Activo</span>
                    <span x-show="exceptionApproved" x-cloak>Auditoría de Excepción Abierta</span>
                </button>
            </div>
        </div>
    </div>

    <!-- 4. Padrón de Entregas Programadas y Proveedores Activos (Exclusivo Gerente y Embarques) -->
    @include('roles.partials.padron-entregas-programadas')

    <!-- Modal: Ficha Ejecutiva de Sucursal Destino -->
    <div x-show="selectedBranchDetail !== null" x-cloak
         class="fixed inset-0 z-50 bg-zinc-950/75 flex items-center justify-center p-4">
        <template x-if="selectedBranchDetail">
            <div @click.outside="selectedBranchDetail = null"
                 class="bg-white text-zinc-950 border-2 border-zinc-950 rounded-lg max-w-xl w-full overflow-hidden shadow-2xl">
                <div class="bg-zinc-950 text-white px-6 py-4 flex items-center justify-between">
                    <div>
                        <span class="px-2 py-0.5 rounded bg-orange-500 text-zinc-950 font-mono text-[10px] font-extrabold uppercase"
                              x-text="'FICHA DE DESTINO · ' + selectedBranchDetail.rack_code"></span>
                        <h3 class="text-lg font-extrabold text-white mt-1"
                            x-text="selectedBranchDetail.code + ' · ' + selectedBranchDetail.name"></h3>
                    </div>
                    <button type="button" @click="selectedBranchDetail = null" class="text-zinc-400 hover:text-white cursor-pointer">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs font-mono">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 bg-zinc-50 border border-zinc-200 rounded p-3.5">
                        <div>
                            <span class="text-[10px] text-zinc-500 block">ZONA / DISTANCIA</span>
                            <strong class="font-sans text-zinc-950" x-text="selectedBranchDetail.zone + ' (' + selectedBranchDetail.distance_km + ' km)'"></strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">TARIMAS EN RACK</span>
                            <strong x-text="selectedBranchDetail.pallets + ' / 32 (' + selectedBranchDetail.rack_pct + '%)'"></strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">ESTATUS TORTÓN</span>
                            <strong :class="selectedBranchDetail.ready_for_torton ? 'text-emerald-700' : 'text-amber-700'"
                                    x-text="selectedBranchDetail.ready_for_torton ? 'LISTA ≥90%' : 'EN ACUMULACIÓN'"></strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">PESO CONSOLIDADO</span>
                            <strong x-text="Number(selectedBranchDetail.weight_kg).toLocaleString('es-MX') + ' kg (' + selectedBranchDetail.weight_pct + '%)'"></strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">VOLUMEN ENVOLVENTE</span>
                            <strong x-text="selectedBranchDetail.volume_m3 + ' m³ (' + selectedBranchDetail.volume_pct + '%)'"></strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">FALTANTE PARA 90%</span>
                            <strong x-text="selectedBranchDetail.missing_weight_kg + ' kg / ' + selectedBranchDetail.missing_volume_m3 + ' m³'"></strong>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-3 bg-zinc-100 border-t border-zinc-200 flex justify-between">
                    <button type="button" @click="selectedBranchDetail = null"
                            class="px-4 py-1.5 rounded border border-zinc-300 bg-white text-xs font-bold uppercase cursor-pointer">
                        Cerrar
                    </button>
                    <div class="space-x-2">
                        <button type="button"
                                @click="selectedBranchIndex = selectedBranchDetail._idx; selectedBranchDetail = null; tab = 'racks'"
                                class="px-4 py-1.5 rounded bg-zinc-950 text-white text-xs font-bold uppercase cursor-pointer">
                            Inspeccionar Rack 4×8
                        </button>
                        <a :href="'/acomodo?branch=' + selectedBranchDetail.code"
                           class="inline-block px-4 py-1.5 rounded bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold uppercase">
                            Abrir en Motor WMS →
                        </a>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
