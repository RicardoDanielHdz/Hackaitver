@php
    $padronItems = ($scheduledArrivals ?? collect())->map(static fn ($a) => [
        'id' => $a->id,
        'folio' => $a->folio,
        'branch_code' => $a->branch?->code ?? 'SUC-01',
        'branch_name' => $a->branch?->name ?? 'Centro Histórico',
        'rack_code' => $a->branch?->rack_code ?? 'RACK-01',
        'sku' => $a->product?->sku ?? 'PAP-007',
        'product_name' => $a->product?->name ?? 'Producto Papelería',
        'stow_zone' => $a->product?->stow_zone ?? 'Base / Pesado',
        'master_factor' => $a->product?->master_factor ?? 10,
        'boxes_expected' => $a->boxes_expected,
        'total_pieces' => $a->boxes_expected * ($a->product?->master_factor ?? 1),
        'total_weight_kg' => round($a->boxes_expected * (float) ($a->product?->master_weight_kg ?? 1), 2),
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

@if(Auth::check() && (Auth::user()->isGerente() || Auth::user()->isJefeEmbarque()))
<div x-data="{
        padronList: @js($padronItems),
        padronSearch: '',
        padronDock: 'TODOS',
        selectedPadronItem: null,
        get filteredPadron() {
            const q = this.padronSearch.trim().toLowerCase();
            return this.padronList.filter(item => {
                const matchDock = this.padronDock === 'TODOS' || item.dock_code === this.padronDock;
                const matchText = !q ||
                    (item.supplier_name || '').toLowerCase().includes(q) ||
                    (item.folio || '').toLowerCase().includes(q) ||
                    (item.sku || '').toLowerCase().includes(q) ||
                    (item.product_name || '').toLowerCase().includes(q) ||
                    (item.branch_code || '').toLowerCase().includes(q) ||
                    (item.branch_name || '').toLowerCase().includes(q);
                return matchDock && matchText;
            });
        }
     }"
     class="bg-white border border-zinc-200 rounded-lg overflow-hidden shadow-2xs">

    <div class="px-5 py-3.5 border-b border-zinc-200 bg-zinc-50/60 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div>
            <div class="flex items-center space-x-2">
                <span class="px-2 py-0.5 rounded bg-zinc-950 text-orange-400 font-mono text-[10px] font-bold uppercase">
                    ACCESO EXCLUSIVO GERENCIA Y EMBARQUES
                </span>
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                    Padrón de Entregas Programadas y Proveedores Activos
                </h3>
            </div>
            <p class="text-[11px] text-zinc-500 mt-0.5">
                Entregas agendadas por proveedores externos para arribo en andenes R1–R4.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 font-mono text-xs">
            <input type="text"
                   x-model="padronSearch"
                   placeholder="Buscar proveedor, folio, SKU o destino..."
                   class="bg-white border border-zinc-300 rounded px-2.5 py-1 text-xs w-56 focus:outline-none focus:border-orange-500">
            <template x-for="dk in ['TODOS', 'R1', 'R2', 'R3', 'R4']" :key="dk">
                <button type="button"
                        @click="padronDock = dk"
                        :class="padronDock === dk ? 'bg-zinc-950 text-white' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200'"
                        class="px-2 py-1 rounded text-[10px] font-bold transition cursor-pointer"
                        x-text="dk"></button>
            </template>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse font-mono tabular-nums">
            <thead>
                <tr class="bg-zinc-950 text-white text-[10px] uppercase tracking-wider">
                    <th class="py-2.5 px-4">Razón Social / Proveedor</th>
                    <th class="py-2.5 px-3">Folio / Factura</th>
                    <th class="py-2.5 px-3">Producto (SKU)</th>
                    <th class="py-2.5 px-3">Destino CDMX</th>
                    <th class="py-2.5 px-3">Andén / Cita</th>
                    <th class="py-2.5 px-3">Volumen Entrega</th>
                    <th class="py-2.5 px-3">Estatus</th>
                    <th class="py-2.5 px-4 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200">
                <template x-for="item in filteredPadron" :key="item.id">
                    <tr class="even:bg-zinc-50/80 hover:bg-orange-50/40 transition">
                        <td class="py-2.5 px-4 font-sans">
                            <div class="font-bold text-zinc-950" x-text="item.supplier_name"></div>
                            <div class="text-[10px] font-mono text-zinc-500" x-text="'RFC: ' + item.supplier_rfc"></div>
                        </td>
                        <td class="py-2.5 px-3 font-bold text-zinc-950" x-text="item.folio"></td>
                        <td class="py-2.5 px-3 font-sans">
                            <span class="font-mono font-extrabold text-zinc-950" x-text="item.sku"></span>
                            <span class="text-zinc-700 block truncate max-w-[200px]" x-text="item.product_name"></span>
                        </td>
                        <td class="py-2.5 px-3 font-sans">
                            <span class="font-mono font-bold text-zinc-950" x-text="item.branch_code"></span>
                            <span class="block text-[11px] text-zinc-600" x-text="item.branch_name"></span>
                        </td>
                        <td class="py-2.5 px-3">
                            <span class="px-2 py-0.5 rounded bg-zinc-950 text-white text-[10px] font-bold" x-text="item.dock_code"></span>
                            <span class="block text-[10px] text-zinc-600 mt-0.5" x-text="item.eta_window"></span>
                        </td>
                        <td class="py-2.5 px-3">
                            <strong class="text-zinc-950" x-text="item.boxes_expected + ' cajas'"></strong>
                            <span class="block text-[10px] text-zinc-500" x-text="item.total_weight_kg + ' kg'"></span>
                        </td>
                        <td class="py-2.5 px-3">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold border bg-emerald-50 border-emerald-300 text-emerald-800"
                                  x-text="item.status"></span>
                        </td>
                        <td class="py-2.5 px-4 text-right whitespace-nowrap space-x-1">
                            <button type="button"
                                    @click="selectedPadronItem = item"
                                    class="px-2.5 py-1 rounded border border-zinc-300 bg-white hover:border-zinc-950 text-zinc-800 text-[10px] font-bold uppercase cursor-pointer">
                                Ver Ficha
                            </button>
                            <a :href="item.qr_label_url" target="_blank"
                               class="inline-block px-2.5 py-1 rounded bg-orange-500 hover:bg-orange-600 text-white text-[10px] font-bold uppercase">
                                QR
                            </a>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <!-- Modal de Ficha del Proveedor y Entrega -->
    <div x-show="selectedPadronItem !== null" x-cloak
         class="fixed inset-0 z-50 bg-zinc-950/75 flex items-center justify-center p-4">
        <template x-if="selectedPadronItem">
            <div @click.outside="selectedPadronItem = null"
                 class="bg-white text-zinc-950 border-2 border-zinc-950 rounded-lg max-w-xl w-full overflow-hidden shadow-2xl">
                <div class="bg-zinc-950 text-white px-6 py-4 flex items-center justify-between">
                    <div>
                        <span class="px-2 py-0.5 rounded bg-orange-500 text-zinc-950 font-mono text-[10px] font-extrabold uppercase">
                            FICHA DE PROVEEDOR Y ENTREGA
                        </span>
                        <h3 class="text-lg font-extrabold text-white mt-1" x-text="selectedPadronItem.supplier_name"></h3>
                    </div>
                    <button type="button" @click="selectedPadronItem = null" class="text-zinc-400 hover:text-white cursor-pointer">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs font-mono">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 bg-zinc-50 border border-zinc-200 rounded p-3.5">
                        <div>
                            <span class="text-[10px] text-zinc-500 block">RFC FISCAL</span>
                            <strong x-text="selectedPadronItem.supplier_rfc"></strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">CONTACTO</span>
                            <strong class="font-sans" x-text="selectedPadronItem.contact_name"></strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">TELÉFONO</span>
                            <strong x-text="selectedPadronItem.contact_phone"></strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">DESTINO CDMX</span>
                            <strong class="font-sans" x-text="selectedPadronItem.branch_code + ' · ' + selectedPadronItem.branch_name"></strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">ANDÉN / CITA</span>
                            <strong x-text="selectedPadronItem.dock_code + ' · ' + selectedPadronItem.eta_window"></strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">VOLUMEN</span>
                            <strong x-text="selectedPadronItem.boxes_expected + ' cajas (' + selectedPadronItem.total_weight_kg + ' kg)'"></strong>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-3 bg-zinc-100 border-t border-zinc-200 flex justify-between">
                    <button type="button" @click="selectedPadronItem = null"
                            class="px-4 py-1.5 rounded border border-zinc-300 bg-white text-xs font-bold uppercase cursor-pointer">
                        Cerrar Ficha
                    </button>
                    <a :href="selectedPadronItem.qr_label_url" target="_blank"
                       class="px-4 py-1.5 rounded bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold uppercase">
                        Imprimir Etiquetas QR
                    </a>
                </div>
            </div>
        </template>
    </div>
</div>
@endif
