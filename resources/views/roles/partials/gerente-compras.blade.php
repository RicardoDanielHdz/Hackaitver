<div x-data="{
        statusFilter: 'TODAS',
        ocSearch: '',
        showNewOcForm: true,
        selectedOrderDetail: null,
        saving: false,
        savedSuccess: false,
        newOcSupplier: 'Distribuidora Metropolitana S.A.',
        newOcDate: '2026-11-26',
        lines: [
            { branch: 'SUC-01 Centro Histórico', sku: 'PAP-007 · Papel bond blanco carta 75g resma 500h', pieces: 240 },
            { branch: 'SUC-04 Iztapalapa Central', sku: 'OFI-006 · Grapas estándar 26/6 caja 5,000 pzas', pieces: 180 }
        ],
        orders: @js($purchaseOrders),
        get filteredOrders() {
            const q = this.ocSearch.trim().toLowerCase();
            return this.orders.filter(o => {
                const matchStatus = this.statusFilter === 'TODAS' || o.status === this.statusFilter;
                const matchText = !q ||
                    o.oc.toLowerCase().includes(q) ||
                    o.supplier.toLowerCase().includes(q) ||
                    o.invoice.toLowerCase().includes(q) ||
                    o.branches.toLowerCase().includes(q);
                return matchStatus && matchText;
            });
        },
        addLine() {
            this.lines.push({ branch: 'SUC-02 Polanco', sku: 'PAP-004 · Cuaderno profesional espiral 100 hojas', pieces: 120 });
        },
        removeLine(index) {
            if (this.lines.length > 1) this.lines.splice(index, 1);
        },
        emitOrder() {
            this.saving = true;
            this.savedSuccess = false;
            setTimeout(() => {
                const nextNum = 107 + this.orders.length;
                const totalPcs = this.lines.reduce((acc, l) => acc + Number(l.pieces || 0), 0);
                this.orders.unshift({
                    oc: 'OC-2026-0' + nextNum,
                    supplier: this.newOcSupplier,
                    rfc: 'DVE940812KM3',
                    invoice: 'Pendiente de registro',
                    branches: this.lines.map(l => l.branch.split(' ')[0]).join(', '),
                    requested_pieces: totalPcs,
                    received_pieces: 0,
                    dock_slot: 'Sin agendar',
                    status: 'EMITIDA'
                });
                this.saving = false;
                this.savedSuccess = true;
            }, 250);
        }
     }"
     class="space-y-5">

    <!-- Notificación de OC Emitida -->
    <div x-show="savedSuccess" x-cloak class="p-3.5 rounded-md bg-zinc-950 text-white border-l-4 border-orange-500 text-xs flex items-center justify-between">
        <span><strong class="text-orange-400">✓ ORDEN DE COMPRA EMITIDA:</strong> Disponible de inmediato en el Portal del Proveedor Externo.</span>
        <button type="button" @click="savedSuccess = false" class="text-zinc-400 hover:text-white cursor-pointer"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <!-- 1. Formulario Administrativo en Cuadrícula Ordenada -->
    <div class="bg-white border-2 border-zinc-950 rounded-lg overflow-hidden shadow-2xs">
        <div class="bg-zinc-950 text-white px-5 py-3 flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <span class="w-5 h-5 rounded bg-orange-500 text-zinc-950 font-mono text-xs font-extrabold flex items-center justify-center">
                    <i class="fa-solid fa-file-invoice"></i>
                </span>
                <h2 class="text-xs font-extrabold uppercase tracking-wider">
                    Emisión de Nueva Orden de Compra (OC)
                </h2>
            </div>
            <button type="button" @click="showNewOcForm = !showNewOcForm"
                    class="text-[11px] font-mono text-orange-400 hover:text-white cursor-pointer"
                    x-text="showNewOcForm ? 'Contraer formulario ▲' : 'Desplegar formulario ▼'"></button>
        </div>

        <form x-show="showNewOcForm" @submit.prevent="emitOrder" class="p-5 space-y-4">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                <!-- Bloque 1 (4/12): Datos Generales del Proveedor -->
                <div class="lg:col-span-4 bg-zinc-50 border border-zinc-200 rounded-md p-4 space-y-3 text-xs">
                    <div class="border-b border-zinc-200 pb-2">
                        <span class="text-[11px] font-extrabold uppercase tracking-wider text-zinc-900">1. Datos del Proveedor</span>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Proveedor Autorizado</label>
                        <select x-model="newOcSupplier" class="w-full bg-white border border-zinc-300 rounded px-3 py-2 text-xs text-zinc-950 focus:outline-none focus:border-orange-500">
                            <option>Distribuidora Metropolitana S.A.</option>
                            <option>Alimentos del Golfo S.A. de C.V.</option>
                            <option>Empaques y Plásticos Olmeca</option>
                            <option>Abarrotera Nacional del Sureste</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Fecha Compromiso de Entrega</label>
                        <input type="date" x-model="newOcDate" required class="w-full bg-white border border-zinc-300 rounded px-3 py-2 font-mono text-xs text-zinc-950 focus:outline-none focus:border-orange-500">
                    </div>
                </div>

                <!-- Bloque 2 (8/12): Partidas por Sucursal Destino y Producto -->
                <div class="lg:col-span-8 bg-zinc-50 border border-zinc-200 rounded-md p-4 space-y-3">
                    <div class="flex items-center justify-between border-b border-zinc-200 pb-2">
                        <span class="text-[11px] font-extrabold uppercase tracking-wider text-zinc-900">2. Partidas Autorizadas por Destino CDMX y SKU</span>
                        <button type="button" @click="addLine"
                                class="px-2.5 py-1 rounded bg-zinc-950 hover:bg-orange-500 text-white text-[10px] font-bold uppercase cursor-pointer">
                            + Añadir Partida
                        </button>
                    </div>

                    <div class="overflow-x-auto bg-white border border-zinc-300 rounded">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-zinc-100 border-b border-zinc-300 font-mono text-[10px] uppercase text-zinc-700">
                                    <th class="py-2 px-3 w-10">#</th>
                                    <th class="py-2 px-3">Sucursal Destino (CDMX)</th>
                                    <th class="py-2 px-3">Producto / SKU Solicitado</th>
                                    <th class="py-2 px-3 w-28">Piezas</th>
                                    <th class="py-2 px-3 w-14 text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200">
                                <template x-for="(line, idx) in lines" :key="idx">
                                    <tr class="even:bg-zinc-50/70">
                                        <td class="py-2 px-3 font-mono font-bold text-zinc-500" x-text="idx + 1"></td>
                                        <td class="py-2 px-3">
                                            <select x-model="line.branch" class="w-full bg-white border border-zinc-300 rounded px-2 py-1.5 text-xs text-zinc-900">
                                                @foreach($branches as $b)
                                                    <option>{{ $b['code'] }} {{ $b['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="py-2 px-3">
                                            <select x-model="line.sku" class="w-full bg-white border border-zinc-300 rounded px-2 py-1.5 text-xs text-zinc-900">
                                                <option>PAP-007 · Papel bond blanco carta 75g resma 500h</option>
                                                <option>PAP-009 · Papel bond blanco oficio 75g resma 500h</option>
                                                <option>OFI-006 · Grapas estándar 26/6 caja 5,000 pzas</option>
                                                <option>PAP-004 · Cuaderno profesional espiral 100 hojas</option>
                                                <option>ADH-012 · Pegamento líquido Resistol 850 1 kg</option>
                                                <option>ARC-011 · Carpeta panorámica 3 argollas 2" carta</option>
                                            </select>
                                        </td>
                                        <td class="py-2 px-3">
                                            <input type="number" min="12" step="12" x-model="line.pieces" required
                                                   class="w-24 bg-white border border-zinc-300 rounded px-2 py-1.5 text-xs font-mono font-bold text-zinc-950">
                                        </td>
                                        <td class="py-2 px-3 text-center">
                                            <button type="button" @click="removeLine(idx)" x-show="lines.length > 1"
                                                    class="text-zinc-400 hover:text-red-600 cursor-pointer">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end pt-1">
                        <button type="submit" :disabled="saving"
                                class="px-5 py-2 rounded bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold uppercase tracking-wider transition cursor-pointer">
                            <i class="fa-solid fa-paper-plane mr-1"></i>
                            <span x-show="!saving">Emitir Orden de Compra y Notificar a Proveedor</span>
                            <span x-show="saving" x-cloak>Generando OC...</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- 2. Barra de Búsqueda y Filtros Rápidos de OC -->
    <div class="bg-white border border-zinc-200 rounded-lg p-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="relative flex-1 max-w-md">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-zinc-400 text-xs"></i>
            <input type="text" x-model="ocSearch" placeholder="Buscar por Folio OC, Proveedor, Factura o Destino..."
                   class="w-full bg-zinc-50 border border-zinc-300 rounded-md pl-8 pr-4 py-1.5 text-xs focus:bg-white focus:outline-none focus:border-orange-500">
        </div>

        <div class="flex flex-wrap gap-1.5 font-mono">
            <template x-for="st in ['TODAS', 'EMITIDA', 'AGENDADA', 'EN RECEPCIÓN', 'COMPLETA']" :key="st">
                <button type="button"
                        @click="statusFilter = st"
                        :class="statusFilter === st ? 'bg-zinc-950 text-white' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200'"
                        class="px-2.5 py-1 rounded text-[10px] font-bold transition cursor-pointer"
                        x-text="st"></button>
            </template>
        </div>
    </div>

    <!-- 3. Tabla Principal Cebrada de Órdenes de Compra -->
    <div class="bg-white border border-zinc-200 rounded-lg overflow-hidden shadow-2xs">
        <div class="px-5 py-3.5 border-b border-zinc-200 bg-zinc-50/60 flex items-center justify-between">
            <div>
                <h2 class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">Padrón Maestro de Órdenes de Compra</h2>
                <p class="text-[11px] text-zinc-500">Regla activa: 1 Factura fiscal irrepetible vinculada a cada Orden de Compra.</p>
            </div>
            <span class="font-mono text-xs font-bold text-zinc-700" x-text="filteredOrders.length + ' registros'"></span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse font-mono tabular-nums">
                <thead>
                    <tr class="bg-zinc-950 text-white text-[10px] uppercase tracking-wider">
                        <th class="py-3 px-4">Folio OC</th>
                        <th class="py-3 px-3">Razón Social / RFC</th>
                        <th class="py-3 px-3">Factura Vinculada</th>
                        <th class="py-3 px-3">Destinos CDMX</th>
                        <th class="py-3 px-3">Avance Piezas</th>
                        <th class="py-3 px-3">Cita Andén</th>
                        <th class="py-3 px-3">Estatus</th>
                        <th class="py-3 px-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    <template x-for="order in filteredOrders" :key="order.oc">
                        <tr class="even:bg-zinc-50/80 hover:bg-orange-50/40 transition">
                            <td class="py-3 px-4 font-extrabold text-zinc-950" x-text="order.oc"></td>
                            <td class="py-3 px-3 font-sans">
                                <div class="font-bold text-zinc-900" x-text="order.supplier"></div>
                                <div class="text-[11px] font-mono text-zinc-500" x-text="'RFC: ' + order.rfc"></div>
                            </td>
                            <td class="py-3 px-3 text-zinc-800" x-text="order.invoice"></td>
                            <td class="py-3 px-3 text-zinc-700" x-text="order.branches"></td>
                            <td class="py-3 px-3">
                                <strong class="text-zinc-950" x-text="order.received_pieces"></strong>
                                <span class="text-zinc-400">/</span>
                                <span class="text-zinc-600" x-text="order.requested_pieces"></span>
                            </td>
                            <td class="py-3 px-3 text-zinc-700" x-text="order.dock_slot"></td>
                            <td class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold border"
                                      :class="order.status === 'COMPLETA'
                                        ? 'bg-emerald-50 border-emerald-300 text-emerald-800'
                                        : (order.status === 'EN RECEPCIÓN' ? 'bg-orange-50 border-orange-300 text-orange-900' : 'bg-zinc-100 border-zinc-300 text-zinc-800')"
                                      x-text="order.status"></span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <button type="button" @click="selectedOrderDetail = order"
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

    <!-- Modal: Ficha de Orden de Compra -->
    <div x-show="selectedOrderDetail !== null" x-cloak
         class="fixed inset-0 z-50 bg-zinc-950/75 flex items-center justify-center p-4">
        <template x-if="selectedOrderDetail">
            <div @click.outside="selectedOrderDetail = null"
                 class="bg-white text-zinc-950 border-2 border-zinc-950 rounded-lg max-w-lg w-full overflow-hidden shadow-2xl">
                <div class="bg-zinc-950 text-white px-6 py-4 flex items-center justify-between">
                    <div>
                        <span class="px-2 py-0.5 rounded bg-orange-500 text-zinc-950 font-mono text-[10px] font-extrabold uppercase">
                            FICHA DE ORDEN DE COMPRA
                        </span>
                        <h3 class="text-lg font-extrabold text-white mt-1" x-text="selectedOrderDetail.oc"></h3>
                    </div>
                    <button type="button" @click="selectedOrderDetail = null" class="text-zinc-400 hover:text-white cursor-pointer">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs font-mono">
                    <div class="grid grid-cols-2 gap-3 bg-zinc-50 border border-zinc-200 rounded p-4">
                        <div class="col-span-2">
                            <span class="text-[10px] text-zinc-500 block">PROVEEDOR AUTORIZADO</span>
                            <strong class="font-sans text-sm text-zinc-950" x-text="selectedOrderDetail.supplier"></strong>
                            <span class="block text-[11px] text-zinc-500" x-text="'RFC: ' + selectedOrderDetail.rfc"></span>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">FACTURA VINCULADA</span>
                            <strong x-text="selectedOrderDetail.invoice"></strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">CITA DE ANDÉN</span>
                            <strong x-text="selectedOrderDetail.dock_slot"></strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">DESTINOS ASIGNADOS</span>
                            <strong x-text="selectedOrderDetail.branches"></strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">AVANCE DE RECEPCIÓN</span>
                            <strong x-text="selectedOrderDetail.received_pieces + ' / ' + selectedOrderDetail.requested_pieces + ' pzas'"></strong>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-3 bg-zinc-100 border-t border-zinc-200 flex justify-end">
                    <button type="button" @click="selectedOrderDetail = null"
                            class="px-4 py-1.5 rounded border border-zinc-300 bg-white text-xs font-bold uppercase cursor-pointer">
                        Cerrar Ficha
                    </button>
                </div>
            </div>
        </template>
    </div>
</div>
