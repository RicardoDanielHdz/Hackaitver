@extends('layouts.app')

@section('title', 'ERP de Recepción y Terminal QR — CEDIS CDMX')
@section('module-code', '03 · OPERADOR CEDIS · RECEPCIÓN QR Y BITÁCORA DE ANDÉN')

@section('extra-head')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
@endsection

@section('content')
<div x-data="handheldScanner()" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

    <!-- 1. Barra Superior Administrativa de Recepción -->
    <div class="bg-white border border-zinc-200 rounded-lg p-5 shadow-2xs">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-4 border-b border-zinc-200">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-2 py-0.5 text-[10px] font-mono font-bold uppercase tracking-widest bg-zinc-950 text-white rounded">
                        ERP RECEPCIÓN · TERMINAL ZEBRA TC22
                    </span>
                    <span class="px-2 py-0.5 text-[10px] font-mono font-semibold bg-zinc-100 border border-zinc-300 text-zinc-700 rounded">
                        ANDÉN R2 ACTIVO
                    </span>
                    <span class="px-2 py-0.5 text-[10px] font-mono font-bold rounded border"
                          :class="palletCompliant ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : 'bg-amber-50 border-amber-300 text-amber-900'"
                          x-text="palletCompliant ? '● NORMA DE TARIMA OK' : '● VERIFICAR CHECKLIST'"></span>
                </div>
                <h1 class="text-xl sm:text-2xl font-extrabold text-zinc-950 tracking-tight mt-1">
                    Recepción de Bultos por QR, Validación de OC y Bitácora de Andén
                </h1>
                <p class="text-xs text-zinc-500 mt-0.5">
                    Verifica las condiciones físicas de la tarima, escanea la etiqueta QR de cada bulto y consulta la ubicación asignada en rack.
                </p>
            </div>

            <!-- Conmutador de Modo del Operador -->
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <a href="{{ route('dashboard.recepcion') }}"
                   class="px-3.5 py-2 rounded-md bg-zinc-950 text-white text-xs font-bold uppercase tracking-wider flex items-center space-x-1.5">
                    <i class="fa-solid fa-qrcode text-orange-400"></i>
                    <span>1. Recepción QR Andén</span>
                </a>
                <a href="{{ route('wms.index') }}"
                   class="px-3.5 py-2 rounded-md bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold uppercase tracking-wider transition flex items-center space-x-1.5">
                    <i class="fa-solid fa-cubes-stacked"></i>
                    <span>2. Motor de Acomodo y Racks →</span>
                </a>
            </div>
        </div>

        <!-- 4 KPIs de Recepción -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 pt-4 font-mono tabular-nums text-xs">
            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block">Orden de Compra Activa</span>
                    <strong class="text-base font-extrabold text-zinc-950" x-text="activeOc"></strong>
                </div>
                <i class="fa-solid fa-file-contract text-zinc-400 text-base"></i>
            </div>

            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block">Avance de Recepción</span>
                    <strong class="text-base font-extrabold text-zinc-950" x-text="scannedCount + ' / 24 bultos'"></strong>
                </div>
                <i class="fa-solid fa-boxes-packing text-orange-500 text-base"></i>
            </div>

            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block">Checklist de Tarima</span>
                    <strong class="text-base font-extrabold"
                            :class="palletCompliant ? 'text-emerald-700' : 'text-amber-700'"
                            x-text="palletCompliant ? '3 / 3 CUMPLIDO' : 'PENDIENTE'"></strong>
                </div>
                <i class="fa-solid fa-clipboard-check text-zinc-400 text-base"></i>
            </div>

            <div class="p-3 rounded-md bg-zinc-50 border border-zinc-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] text-zinc-500 uppercase block">Incidencias Turno</span>
                    <strong class="text-base font-extrabold text-zinc-950">{{ count($incidents) }} registros</strong>
                </div>
                <i class="fa-solid fa-shield-halved text-zinc-400 text-base"></i>
            </div>
        </div>
    </div>

    <!-- 2. Cuerpo Principal en 2 Columnas: Lector QR y Checklist (5/12) + Dictamen y Tabla de Bitácora (7/12) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

        <!-- Columna Izquierda (5/12): Checklist Normativo + Lector Óptico QR -->
        <div class="lg:col-span-5 space-y-5">
            <!-- Bloque 1: Checklist Normativo -->
            <div class="bg-white border border-zinc-200 rounded-lg p-4 space-y-3 shadow-2xs">
                <div class="flex items-center justify-between border-b border-zinc-200 pb-2">
                    <span class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                        1. Checklist Normativo de Tarima
                    </span>
                    <span class="font-mono text-[10px] font-bold px-2 py-0.5 rounded border"
                          :class="palletCompliant ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : 'bg-amber-50 border-amber-300 text-amber-800'"
                          x-text="palletCompliant ? 'NORMA OK' : 'INCOMPLETO'"></span>
                </div>

                <div class="grid grid-cols-3 gap-2 text-xs font-semibold">
                    <button type="button"
                            @click="checkBase = !checkBase"
                            :class="checkBase ? 'bg-zinc-950 text-white border-zinc-950' : 'bg-zinc-50 text-zinc-500 border-zinc-300'"
                            class="py-2.5 px-2 rounded border text-center transition cursor-pointer">
                        <div class="font-mono text-[10px] font-bold" x-text="checkBase ? '✓ OK' : 'PEND'"></div>
                        <div class="mt-0.5 text-[11px]">Base 1.12×1.15m</div>
                    </button>

                    <button type="button"
                            @click="checkFilm = !checkFilm"
                            :class="checkFilm ? 'bg-zinc-950 text-white border-zinc-950' : 'bg-zinc-50 text-zinc-500 border-zinc-300'"
                            class="py-2.5 px-2 rounded border text-center transition cursor-pointer">
                        <div class="font-mono text-[10px] font-bold" x-text="checkFilm ? '✓ OK' : 'PEND'"></div>
                        <div class="mt-0.5 text-[11px]">Film Playo</div>
                    </button>

                    <button type="button"
                            @click="checkHeight = !checkHeight"
                            :class="checkHeight ? 'bg-zinc-950 text-white border-zinc-950' : 'bg-zinc-50 text-zinc-500 border-zinc-300'"
                            class="py-2.5 px-2 rounded border text-center transition cursor-pointer">
                        <div class="font-mono text-[10px] font-bold" x-text="checkHeight ? '✓ OK' : 'PEND'"></div>
                        <div class="mt-0.5 text-[11px]">&le; 1.85 m</div>
                    </button>
                </div>
            </div>

            <!-- Bloque 2: Visor Óptico QR -->
            <div class="bg-white border border-zinc-200 rounded-lg p-4 space-y-3 shadow-2xs">
                <div class="flex items-center justify-between border-b border-zinc-200 pb-2">
                    <span class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                        2. Lector Óptico QR 2D
                    </span>
                    <a href="/qr-muestra.svg" target="_blank" class="text-[11px] font-mono font-bold text-orange-600 hover:underline">
                        Abrir QR Muestra ↗
                    </a>
                </div>

                <div class="relative aspect-square max-h-64 mx-auto bg-zinc-950 rounded border border-zinc-300 overflow-hidden flex flex-col items-center justify-center">
                    <div id="handheld-qr-reader" class="w-full h-full"></div>

                    <div x-show="cameraState !== 'scanning'" class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center bg-zinc-950 text-white">
                        <div class="w-32 h-32 relative flex items-center justify-center mb-3">
                            <span class="absolute top-0 left-0 w-5 h-5 border-t-2 border-l-2 border-orange-500"></span>
                            <span class="absolute top-0 right-0 w-5 h-5 border-t-2 border-r-2 border-orange-500"></span>
                            <span class="absolute bottom-0 left-0 w-5 h-5 border-b-2 border-l-2 border-orange-500"></span>
                            <span class="absolute bottom-0 right-0 w-5 h-5 border-b-2 border-r-2 border-orange-500"></span>
                            <i class="fa-solid fa-qrcode text-4xl text-zinc-500" x-show="cameraState === 'idle'"></i>
                            <i class="fa-solid fa-spinner fa-spin text-3xl text-orange-500" x-show="cameraState === 'loading'" x-cloak></i>
                        </div>
                        <p class="text-xs font-semibold text-zinc-200"
                           x-text="cameraState === 'loading' ? 'Inicializando cámara...' : 'Lector óptico 2D listo para captura'"></p>
                    </div>
                </div>

                <div x-show="cameraError !== ''" x-cloak class="p-3 rounded bg-red-50 border border-red-300 text-xs text-red-900">
                    <p x-text="cameraError"></p>
                </div>

                <div class="flex gap-2">
                    <button x-show="cameraState !== 'scanning'"
                            type="button"
                            @click="startCamera()"
                            :disabled="!palletCompliant || cameraState === 'loading'"
                            class="w-full bg-orange-500 hover:bg-orange-600 disabled:bg-zinc-300 text-white font-bold text-xs uppercase tracking-wider py-2.5 rounded transition cursor-pointer flex items-center justify-center space-x-2">
                        <i class="fa-solid fa-camera"></i>
                        <span>Activar Escáner QR</span>
                    </button>

                    <button x-show="cameraState === 'scanning'" x-cloak
                            type="button"
                            @click="stopCamera()"
                            class="w-full bg-zinc-950 text-white font-bold text-xs uppercase tracking-wider py-2.5 rounded transition cursor-pointer flex items-center justify-center space-x-2">
                        <i class="fa-solid fa-stop"></i>
                        <span>Detener Lector</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Columna Derecha (7/12): Dictamen de Escaneo + Bitácora Cebrada de Recepción e Incidencias -->
        <div class="lg:col-span-7 space-y-5">
            <!-- Bloque 3: Resultado del Último Escaneo -->
            <div class="bg-white border border-zinc-200 rounded-lg p-5 shadow-2xs">
                <div class="border-b border-zinc-200 pb-2.5 mb-3 flex items-center justify-between">
                    <span class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                        3. Dictamen de Validación y Ubicación en Rack
                    </span>
                    <span class="text-[11px] font-mono text-zinc-500">Validación contra OC y Catálogo 72 SKUs</span>
                </div>

                <template x-if="scanResult !== null && scanResult.accepted">
                    <div class="p-4 rounded-md bg-emerald-50 border border-emerald-300 space-y-3">
                        <div class="flex items-center justify-between border-b border-emerald-200 pb-2">
                            <span class="text-xs font-extrabold uppercase text-emerald-900">
                                ✓ BULTO VALIDADO Y ACEPTADO
                            </span>
                            <span class="font-mono text-xs font-bold text-emerald-800" x-text="scanResult.bulto"></span>
                        </div>
                        <div class="grid grid-cols-3 gap-3 text-xs font-mono">
                            <div>
                                <span class="text-[10px] text-emerald-700 uppercase block">SKU Producto</span>
                                <strong class="text-zinc-950" x-text="scanResult.sku"></strong>
                            </div>
                            <div>
                                <span class="text-[10px] text-emerald-700 uppercase block">Cantidad</span>
                                <strong class="text-zinc-950" x-text="scanResult.piezas"></strong>
                            </div>
                            <div>
                                <span class="text-[10px] text-emerald-700 uppercase block">Destino CDMX</span>
                                <strong class="text-zinc-950" x-text="scanResult.sucursal"></strong>
                            </div>
                        </div>
                        <div class="p-3 rounded bg-zinc-950 text-white font-mono text-xs flex items-center justify-between">
                            <span class="text-orange-400 font-bold uppercase">Ubicación Asignada:</span>
                            <strong class="text-sm" x-text="scanResult.location"></strong>
                        </div>
                    </div>
                </template>

                <template x-if="scanResult !== null && !scanResult.accepted">
                    <div class="p-4 rounded-md bg-red-50 border border-red-300 text-red-950 space-y-1">
                        <div class="text-xs font-extrabold uppercase text-red-800">✕ RECHAZADO · NO RECIBIR BULTO</div>
                        <div class="text-sm font-bold" x-text="scanResult.reason"></div>
                        <p class="text-xs text-red-700">Incidencia registrada automáticamente en bitácora. Separe la caja en área de devolución.</p>
                    </div>
                </template>

                <div x-show="scanResult === null" class="py-6 text-center border border-dashed border-zinc-300 rounded-md text-xs font-mono text-zinc-500">
                    Esperando lectura de etiqueta QR (`bulto`, `oc`, `sucursal`/`destino`, `sku`, `piezas`).
                </div>
            </div>

            <!-- Bloque 4: Tabla Cebrada de Bitácora de Incidencias en Andén -->
            <div class="bg-white border border-zinc-200 rounded-lg overflow-hidden shadow-2xs">
                <div class="px-5 py-3.5 border-b border-zinc-200 bg-zinc-50/60 flex items-center justify-between">
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                        Bitácora de Incidencias Registradas en Recepción
                    </h3>
                    <span class="font-mono text-[11px] text-zinc-500">{{ count($incidents) }} eventos</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse font-mono tabular-nums">
                        <thead>
                            <tr class="bg-zinc-950 text-white text-[10px] uppercase">
                                <th class="py-2.5 px-3">Hora / Folio</th>
                                <th class="py-2.5 px-3">Tipo</th>
                                <th class="py-2.5 px-3">OC / Bulto</th>
                                <th class="py-2.5 px-3">Motivo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            @foreach($incidents as $inc)
                                <tr class="even:bg-zinc-50/80 hover:bg-orange-50/40">
                                    <td class="py-2.5 px-3 whitespace-nowrap">
                                        <strong class="text-zinc-950">{{ $inc['time'] }}</strong>
                                        <span class="block text-[10px] text-zinc-500">{{ $inc['code'] }}</span>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 border border-amber-300 text-amber-900">
                                            {{ $inc['type'] }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 whitespace-nowrap">{{ $inc['oc'] }} · {{ $inc['package'] }}</td>
                                    <td class="py-2.5 px-3 font-sans text-zinc-700">{{ $inc['detail'] }}</td>
                                </tr>
                            @endforeach
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
function handheldScanner() {
    return {
        activeOc: 'OC-2026-0104',
        scannedCount: 18,
        checkBase: true,
        checkFilm: true,
        checkHeight: true,
        cameraState: 'idle',
        cameraError: '',
        qrInstance: null,
        lastCode: '',
        scanResult: null,
        seenPackages: [],
        get palletCompliant() {
            return this.checkBase && this.checkFilm && this.checkHeight;
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
                if (!this.qrInstance) {
                    this.qrInstance = new Html5Qrcode('handheld-qr-reader');
                }
                await this.qrInstance.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 210, height: 210 } },
                    (decodedText) => this.handleDecodedQr(decodedText),
                    () => {}
                );
                this.cameraState = 'scanning';
            } catch (err) {
                this.cameraState = 'idle';
                this.cameraError = 'No fue posible abrir la cámara. Asegúrate de acceder mediante HTTPS o localhost y otorgar permisos.';
            }
        },
        async stopCamera() {
            if (this.qrInstance && this.cameraState === 'scanning') {
                try {
                    await this.qrInstance.stop();
                } catch (_) {}
            }
            this.cameraState = 'idle';
        },
        async handleDecodedQr(rawText) {
            if (rawText === this.lastCode) return;
            this.lastCode = rawText;
            setTimeout(() => { this.lastCode = ''; }, 2000);

            let parsed = null;
            try {
                parsed = JSON.parse(rawText);
            } catch (_) {
                this.beep(220, 240);
                this.scanResult = {
                    accepted: false,
                    reason: 'Código QR ajeno al estándar CEDIS: ' + rawText.slice(0, 40)
                };
                return;
            }

            const bulto = parsed.bulto || parsed.folio || 'SIN-FOLIO';
            const sku = parsed.sku || '';
            const sucursal = parsed.destino || parsed.sucursal || 'SUC-01';
            const piezas = Number(parsed.piezas || 0);
            const legacySkus = ['SKU-ACE-900', 'SKU-DET-100', 'SKU-CAF-500'];

            if (this.seenPackages.includes(bulto)) {
                this.beep(220, 240);
                this.scanResult = {
                    accepted: false,
                    reason: 'BULTO DUPLICADO (' + bulto + ' ya fue escaneado)'
                };
                return;
            }

            if (!legacySkus.includes(sku)) {
                try {
                    const res = await fetch(@js(route('wms.qr.scan', [], false)), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': @js(csrf_token())
                        },
                        body: JSON.stringify({ qr_payload: rawText })
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        this.beep(220, 240);
                        this.scanResult = {
                            accepted: false,
                            reason: data.message || ('SKU NO SOLICITADO (' + sku + ')')
                        };
                        return;
                    }

                    this.seenPackages.push(bulto);
                    this.scannedCount++;
                    this.beep(880, 120);

                    const alloc = data.result.allocations[0];
                    const loc = alloc.rack_assignment
                        ? alloc.rack_assignment.coordinate
                        : (alloc.pallet_code + ' · ZONA ' + alloc.assigned_zone);

                    this.scanResult = {
                        accepted: true,
                        bulto: bulto,
                        sku: data.result.product_sku,
                        piezas: piezas > 0 ? (piezas + ' pzas') : (data.result.boxes_processed + ' cajas'),
                        sucursal: data.state.branch.code + ' · ' + data.state.branch.name,
                        location: alloc.pallet_code + ' → ' + loc
                    };
                    return;
                } catch (_) {}
            }

            if (!legacySkus.includes(sku)) {
                this.beep(220, 240);
                this.scanResult = {
                    accepted: false,
                    reason: 'SKU NO SOLICITADO EN LA OC (' + (sku || 'DESCONOCIDO') + ')'
                };
                return;
            }

            if (this.scannedCount >= 24) {
                this.beep(220, 240);
                this.scanResult = {
                    accepted: false,
                    reason: 'EXCEDENTE DETECTADO: La OC ya cubrió los 24 bultos solicitados'
                };
                return;
            }

            this.seenPackages.push(bulto);
            this.scannedCount++;
            this.beep(880, 120);

            const rackNum = sucursal.replace('SUC-', 'RACK-');
            const pos = ((this.scannedCount % 8) + 1);
            this.scanResult = {
                accepted: true,
                bulto: bulto,
                sku: sku,
                piezas: (piezas > 0 ? piezas : 24) + ' pzas',
                sucursal: sucursal,
                location: rackNum + ' · NIVEL 1 · POS 0' + pos
            };
        }
    };
}
</script>
@endsection
