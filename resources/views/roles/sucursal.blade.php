@extends('layouts.app')

@section('title', 'ERP de Recepción en Sucursal Destino — CEDIS CDMX')
@section('module-code', '05 · RECEPTOR DE SUCURSAL · ACUSE DE MANIFIESTO QR')

@section('extra-head')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
@endsection

@section('content')
<div x-data="branchReceiver()" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

    <!-- 1. Barra Superior Administrativa de Sucursal Destino -->
    <div class="bg-white border border-zinc-200 rounded-lg p-5 shadow-2xs">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-4 border-b border-zinc-200">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-2 py-0.5 text-[10px] font-mono font-bold uppercase tracking-widest bg-zinc-950 text-white rounded">
                        ERP DESTINO DE ENTREGA · CDMX
                    </span>
                    <span class="px-2 py-0.5 text-[10px] font-mono font-semibold bg-zinc-100 border border-zinc-300 text-zinc-700 rounded">
                        DESTINO: {{ $user->assigned_branch_code ?? 'SUC-01' }} · Centro Histórico
                    </span>
                    <span class="px-2 py-0.5 text-[10px] font-mono font-bold rounded border"
                          :class="confirmed ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : 'bg-amber-50 border-amber-300 text-amber-800'"
                          x-text="confirmed ? '● CICLO CERRADO' : '● 1 TORTÓN EN ARRIBO'"></span>
                </div>
                <h1 class="text-xl sm:text-2xl font-extrabold text-zinc-950 tracking-tight mt-1">
                    Recepción de Embarques en Tienda y Acuse de Manifiesto QR
                </h1>
                <p class="text-xs text-zinc-500 mt-0.5">
                    Responsable en sucursal: <strong>{{ $user->name }}</strong>. Verifica marchamo y tarimas antes de firmar el cierre del viaje.
                </p>
            </div>
        </div>

        <!-- 4 KPIs de Sucursal -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 pt-4 font-mono tabular-nums text-xs">
            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200">
                <span class="text-[10px] text-zinc-500 uppercase block">Manifiesto Activo</span>
                <strong class="text-base font-extrabold text-zinc-950" x-text="manifestFolio"></strong>
            </div>
            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200">
                <span class="text-[10px] text-zinc-500 uppercase block">Unidad Tortón</span>
                <strong class="text-base font-extrabold text-zinc-950">55-XA-2F · E1</strong>
            </div>
            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200">
                <span class="text-[10px] text-zinc-500 uppercase block">Carga Consolidada</span>
                <strong class="text-base font-extrabold text-zinc-950">12 Tarimas (95.2%)</strong>
            </div>
            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200">
                <span class="text-[10px] text-zinc-500 uppercase block">Estatus Recepción</span>
                <strong class="text-base font-extrabold"
                        :class="confirmed ? 'text-emerald-700' : 'text-amber-700'"
                        x-text="confirmed ? 'RECIBIDO OK' : 'PENDIENTE'"></strong>
            </div>
        </div>
    </div>

    <!-- 2. Cuerpo en 2 Columnas: Escáner QR de Manifiesto (5/12) + Verificación y Tabla de Historial (7/12) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

        <!-- Izquierda (5/12): Lector QR Maestro -->
        <div class="lg:col-span-5 bg-white border border-zinc-200 rounded-lg p-5 space-y-3 shadow-2xs">
            <div class="border-b border-zinc-200 pb-2.5">
                <span class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                    1. Escaneo de QR Maestro (Relación de Embarque)
                </span>
            </div>

            <div class="relative w-full max-w-[260px] aspect-square mx-auto bg-zinc-950 rounded-md border border-zinc-300 overflow-hidden flex flex-col items-center justify-center">
                <div id="branch-qr-reader" class="absolute inset-0 w-full h-full"></div>

                <div x-show="cameraState !== 'scanning'" class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center bg-zinc-950 text-white">
                    <div class="w-28 h-28 relative flex items-center justify-center mb-3">
                        <span class="absolute top-0 left-0 w-5 h-5 border-t-2 border-l-2 border-orange-500"></span>
                        <span class="absolute top-0 right-0 w-5 h-5 border-t-2 border-r-2 border-orange-500"></span>
                        <span class="absolute bottom-0 left-0 w-5 h-5 border-b-2 border-l-2 border-orange-500"></span>
                        <span class="absolute bottom-0 right-0 w-5 h-5 border-b-2 border-r-2 border-orange-500"></span>
                        <i class="fa-solid fa-file-contract text-3xl text-zinc-500" x-show="cameraState === 'idle'"></i>
                        <i class="fa-solid fa-spinner fa-spin text-2xl text-orange-500" x-show="cameraState === 'loading'" x-cloak></i>
                    </div>
                    <p class="text-xs font-semibold text-zinc-200"
                       x-text="cameraState === 'loading' ? 'Procesando cámara / QR...' : 'Apunta al código QR impreso en la Relación de Embarque entregada por el chofer'"></p>
                </div>
            </div>

            <div x-show="cameraError !== ''" x-cloak class="p-3 rounded bg-red-50 border border-red-300 text-xs text-red-900">
                <p x-text="cameraError"></p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <button x-show="cameraState !== 'scanning'"
                        type="button"
                        @click="startCamera()"
                        class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold text-xs uppercase tracking-wider py-2.5 px-3 rounded transition cursor-pointer flex items-center justify-center space-x-2">
                    <i class="fa-solid fa-video"></i>
                    <span>Cámara en Vivo</span>
                </button>

                <button x-show="cameraState === 'scanning'" x-cloak
                        type="button"
                        @click="stopCamera()"
                        class="w-full bg-zinc-950 text-white font-bold text-xs uppercase tracking-wider py-2.5 px-3 rounded transition cursor-pointer">
                    Detener Cámara
                </button>

                <label class="w-full bg-zinc-950 hover:bg-zinc-800 text-white font-bold text-xs uppercase tracking-wider py-2.5 px-3 rounded transition cursor-pointer flex items-center justify-center space-x-2 text-center">
                    <i class="fa-solid fa-camera text-orange-400"></i>
                    <span>Foto Cámara Móvil</span>
                    <input type="file" accept="image/*" capture="environment" @change="scanQrPhoto($event)" class="hidden">
                </label>
            </div>
        </div>

        <!-- Derecha (7/12): Verificación de Arribo + Padrón de Embarques Recibidos en Tienda -->
        <div class="lg:col-span-7 space-y-5">
            <div class="bg-white border border-zinc-200 rounded-lg p-5 space-y-4 shadow-2xs">
                <div class="flex items-center justify-between border-b border-zinc-200 pb-2.5">
                    <div>
                        <span class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                            2. Verificación Física y Firma de Acuse
                        </span>
                    </div>
                    <span class="px-2.5 py-0.5 rounded font-mono text-[10px] font-bold border"
                          :class="confirmed ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : 'bg-amber-50 border-amber-300 text-amber-800'"
                          x-text="confirmed ? 'CERRADO EN TIENDA' : 'ARRIBO PENDIENTE'"></span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3.5 rounded bg-zinc-50 border border-zinc-200 text-xs">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" x-model="sealIntact" class="rounded border-zinc-300 text-orange-600">
                        <span class="text-zinc-800 font-semibold">Marchamo de caja y playo íntegros</span>
                    </label>
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" x-model="palletsCounted" class="rounded border-zinc-300 text-orange-600">
                        <span class="text-zinc-800 font-semibold">12 tarimas completas descargadas</span>
                    </label>
                </div>

                <div x-show="confirmed" x-cloak class="p-3.5 rounded bg-emerald-50 border border-emerald-300 text-emerald-950 text-xs">
                    <strong class="font-mono">✓ EMBARQUE CERRADO:</strong> Acuse registrado con fecha, hora y firma de {{ $user->name }}.
                </div>

                <button x-show="!confirmed"
                        type="button"
                        @click="confirmArrival()"
                        :disabled="!sealIntact || !palletsCounted"
                        class="w-full bg-zinc-950 hover:bg-orange-600 disabled:bg-zinc-300 text-white font-bold text-xs uppercase tracking-wider py-3 rounded transition cursor-pointer">
                    Confirmar Recepción de 12 Tarimas y Cerrar Viaje
                </button>
            </div>

            <!-- Tabla Cebrada de Historial de Recepciones en Tienda -->
            <div class="bg-white border border-zinc-200 rounded-lg overflow-hidden shadow-2xs">
                <div class="px-5 py-3.5 border-b border-zinc-200 bg-zinc-50/60">
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                        Historial de Manifiestos Recibidos en {{ $user->assigned_branch_code ?? 'SUC-01' }}
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse font-mono tabular-nums">
                        <thead>
                            <tr class="bg-zinc-950 text-white text-[10px] uppercase">
                                <th class="py-2.5 px-4">Folio Manifiesto</th>
                                <th class="py-2.5 px-3">Unidad / Andén</th>
                                <th class="py-2.5 px-3">Tarimas / Bultos</th>
                                <th class="py-2.5 px-4 text-right">Estatus</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            <template x-for="h in history" :key="h.folio">
                                <tr class="even:bg-zinc-50/80 hover:bg-orange-50/40">
                                    <td class="py-2.5 px-4 font-bold text-zinc-950" x-text="h.folio"></td>
                                    <td class="py-2.5 px-3 text-zinc-700" x-text="h.unit"></td>
                                    <td class="py-2.5 px-3 text-zinc-800" x-text="h.cargo"></td>
                                    <td class="py-2.5 px-4 text-right">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border"
                                              :class="h.status === 'CERRADO' ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : 'bg-amber-50 border-amber-300 text-amber-800'"
                                              x-text="h.status"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@section('extra-scripts')
<script>
function branchReceiver() {
    return {
        manifestFolio: 'EMB-2026-042',
        cameraState: 'idle',
        cameraError: '',
        qrInstance: null,
        sealIntact: true,
        palletsCounted: true,
        confirmed: false,
        history: [
            { folio: 'EMB-2026-042', unit: '55-XA-2F · E1', cargo: '12 Tarimas · 144 Bultos (95.2%)', status: 'EN ARRIBO' },
            { folio: 'EMB-2026-036', unit: '71-VR-8B · E2', cargo: '12 Tarimas · 138 Bultos (93.8%)', status: 'CERRADO' },
            { folio: 'EMB-2026-029', unit: '44-MX-9C · E1', cargo: '12 Tarimas · 140 Bultos (94.1%)', status: 'CERRADO' }
        ],
        confirmArrival() {
            this.confirmed = true;
            this.history[0].status = 'CERRADO';
        },
        parseManifestQr(decodedText) {
            try {
                const parsed = JSON.parse(decodedText);
                if (parsed && parsed.folio) {
                    this.manifestFolio = String(parsed.folio);
                    return;
                }
            } catch (_) {}
            this.manifestFolio = String(decodedText).slice(0, 32);
        },
        async startCamera() {
            this.cameraError = '';
            this.cameraState = 'loading';
            try {
                await this.stopCamera();
                this.qrInstance = await window.CedisQrScanner.startLiveCamera(
                    'branch-qr-reader',
                    (decodedText) => {
                        this.parseManifestQr(decodedText);
                        this.stopCamera();
                    }
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
                this.parseManifestQr(decoded);
            } catch (_) {
                this.cameraError = 'No se detectó un QR legible en la foto. Acerca la cámara al código con buena luz.';
            } finally {
                this.cameraState = 'idle';
                if (event.target) {
                    event.target.value = '';
                }
            }
        }
    };
}
</script>
@endsection
