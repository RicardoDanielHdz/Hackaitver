<div x-data="{
        requests: @js($emergencyRequests ?? []),
        csrfToken: @js(csrf_token()),
        listUrl: @js(route('embarques.emergencies.index', [], false)),
        notesById: {},
        processingId: null,
        bannerMsg: '',
        errorMsg: '',
        init() {
            setInterval(() => this.refreshList(), 6000);
        },
        get pendingList() {
            return this.requests.filter(r => r.status === 'pending');
        },
        get resolvedList() {
            return this.requests.filter(r => r.status !== 'pending').slice(0, 6);
        },
        async refreshList() {
            try {
                const res = await fetch(this.listUrl, { headers: { 'Accept': 'application/json' } });
                if (res.ok) {
                    const data = await res.json();
                    this.requests = data.requests || [];
                }
            } catch (_) {}
        },
        async decide(req, action) {
            this.processingId = req.id;
            this.errorMsg = '';
            try {
                const url = '/gerente/emergencias/' + req.id + '/' + action;
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken
                    },
                    body: JSON.stringify({
                        manager_notes: this.notesById[req.id] || ''
                    })
                });
                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data.message || 'No se pudo procesar la solicitud.');
                }
                this.requests = data.requests || [];
                this.bannerMsg = data.message;
            } catch (err) {
                this.errorMsg = err.message;
            } finally {
                this.processingId = null;
            }
        }
     }"
     class="space-y-3">

    <!-- Confirmación de acción gerencial -->
    <div x-show="bannerMsg !== ''" x-cloak
         class="p-3.5 rounded-md bg-zinc-950 text-white border-l-4 border-emerald-500 text-xs flex items-center justify-between gap-3">
        <span><strong class="text-emerald-400">✓ DICTAMEN GERENCIAL EJECUTADO:</strong> <span x-text="bannerMsg"></span></span>
        <button type="button" @click="bannerMsg = ''" class="text-zinc-400 hover:text-white cursor-pointer"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <div x-show="errorMsg !== ''" x-cloak
         class="p-3.5 rounded-md bg-red-950 text-white border-l-4 border-red-500 text-xs flex items-center justify-between">
        <span x-text="errorMsg"></span>
        <button type="button" @click="errorMsg = ''" class="text-red-300 hover:text-white cursor-pointer"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <!-- ALERTA URGENTE EN VIVO CUANDO EMBARQUES SOLICITA FORZAR SALIDA (<90%) -->
    <div x-show="pendingList.length > 0" x-cloak
         class="bg-white border-2 border-red-600 rounded-lg overflow-hidden shadow-md">
        <div class="bg-red-700 text-white px-5 py-3 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center space-x-2.5">
                <span class="w-6 h-6 rounded bg-white text-red-700 font-mono text-xs font-extrabold flex items-center justify-center">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </span>
                <div>
                    <span class="text-[10px] font-mono font-bold uppercase tracking-widest text-red-100 block">
                        NOTIFICACIÓN DE URGENCIA · CANDADO TORTÓN (&lt;90%)
                    </span>
                    <h2 class="text-sm font-extrabold uppercase tracking-wide">
                        Solicitud(es) de Salida de Emergencia Pendientes de tu Autorización (<span x-text="pendingList.length"></span>)
                    </h2>
                </div>
            </div>
            <span class="px-2.5 py-0.5 rounded bg-red-950 text-white font-mono text-[10px] font-bold uppercase">
                ACTUALIZACIÓN EN VIVO
            </span>
        </div>

        <div class="p-4 space-y-3">
            <template x-for="req in pendingList" :key="req.id">
                <div class="border border-red-300 bg-red-50/50 rounded-md p-4 space-y-3 text-xs">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2 font-mono">
                                <span class="px-2 py-0.5 rounded bg-zinc-950 text-white text-[10px] font-bold" x-text="req.folio"></span>
                                <strong class="text-sm font-sans font-extrabold text-zinc-950" x-text="req.branch_code + ' · ' + req.branch_name + ' (' + req.rack_code + ')'"></strong>
                                <span class="px-2 py-0.5 rounded bg-amber-100 border border-amber-400 text-amber-950 text-[10px] font-bold"
                                      x-text="req.estimated_pallets + '/12 tar · ' + req.weight_pct + '% Peso · ' + req.volume_pct + '% Vol'"></span>
                            </div>
                            <div class="text-zinc-800">
                                <strong>Motivo de Emergencia:</strong> <span class="font-semibold text-red-900" x-text="req.emergency_reason"></span>
                            </div>
                            <div class="font-mono text-[11px] text-zinc-600">
                                Solicita: <strong x-text="req.requested_by"></strong> ·
                                Andén <strong x-text="req.dock_code"></strong> ·
                                Unidad <strong x-text="req.unit_plate"></strong> (<span x-text="req.driver_name"></span>) ·
                                <span x-text="req.created_at"></span>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 shrink-0">
                            <input type="text"
                                   x-model="notesById[req.id]"
                                   placeholder="Nota gerencial (opcional)..."
                                   class="bg-white border border-zinc-300 rounded px-2.5 py-2 text-xs w-56 focus:outline-none focus:border-zinc-950">
                            <button type="button"
                                    :disabled="processingId === req.id"
                                    @click="decide(req, 'aprobar')"
                                    class="px-4 py-2 rounded bg-emerald-700 hover:bg-emerald-800 disabled:bg-zinc-300 text-white font-mono text-[11px] font-bold uppercase cursor-pointer">
                                <i class="fa-solid fa-check mr-1"></i> Aceptar Salida y Generar PDF
                            </button>
                            <button type="button"
                                    :disabled="processingId === req.id"
                                    @click="decide(req, 'rechazar')"
                                    class="px-3 py-2 rounded border border-red-400 bg-white hover:bg-red-100 text-red-800 font-mono text-[11px] font-bold uppercase cursor-pointer">
                                Rechazar
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Historial Compacto de Salidas de Emergencia Procesadas -->
    <div x-show="resolvedList.length > 0" x-cloak
         class="bg-white border border-zinc-200 rounded-lg p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs font-mono">
        <div class="flex flex-wrap items-center gap-2">
            <span class="px-2 py-0.5 rounded bg-zinc-950 text-orange-400 text-[10px] font-bold uppercase">HISTORIAL EMERGENCIAS</span>
            <span class="font-sans text-zinc-600">Última resolución:</span>
            <strong class="text-zinc-950" x-text="resolvedList[0]?.folio + ' · ' + resolvedList[0]?.branch_code"></strong>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold border"
                  :class="resolvedList[0]?.status === 'approved' ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : 'bg-red-50 border-red-300 text-red-800'"
                  x-text="resolvedList[0]?.status === 'approved' ? ('APROBADA → ' + (resolvedList[0]?.outbound_folio || '')) : 'RECHAZADA'"></span>
        </div>
        <template x-if="resolvedList[0]?.manifest_url">
            <a :href="resolvedList[0].manifest_url" target="_blank"
               class="px-3 py-1.5 rounded bg-orange-500 hover:bg-orange-600 text-white text-[10px] font-bold uppercase self-start sm:self-auto">
                <i class="fa-solid fa-file-pdf mr-1"></i> Ver Etiqueta / PDF Generado ↗
            </a>
        </template>
    </div>
</div>
