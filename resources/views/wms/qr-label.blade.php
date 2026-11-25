<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiquetas QR de Bulto · {{ $product->sku }} → {{ $branch->code }} — CEDIS CDMX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .mono { font-family: 'JetBrains Mono', monospace; font-variant-numeric: tabular-nums; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
        }
    </style>
</head>
<body class="bg-zinc-100 text-zinc-950 py-6 px-4">
    <div class="max-w-5xl mx-auto space-y-6">
        <!-- Barra superior no imprimible -->
        <div class="no-print bg-white border border-zinc-300 rounded-md p-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('wms.index', ['branch' => $branch->code]) }}"
                   class="text-xs font-bold uppercase text-zinc-700 hover:text-zinc-950">
                    ← Volver al Motor de Acomodo (CEDIS CDMX)
                </a>
                <p class="text-xs text-zinc-500 mt-0.5">
                    Escanea cualquiera de estos QR con la cámara o cópialo en el lector QR de `/acomodo` para registrar su almacenamiento en rack.
                </p>
            </div>
            <button type="button" onclick="window.print()"
                    class="px-4 py-2 rounded bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold uppercase cursor-pointer">
                Imprimir Etiquetas QR (4×6&quot;)
            </button>
        </div>

        <!-- Etiqueta Maestra de Lote (Para escanear todos los bultos de la partida en 1 lectura) -->
        <div class="bg-white border-2 border-zinc-950 rounded-md p-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b-2 border-zinc-950 pb-4 mb-4">
                <div>
                    <div class="mono text-[10px] font-bold uppercase tracking-widest text-orange-600">
                        CEDIS ÚNICO CDMX · ETIQUETA QR MAESTRA DE LOTE ({{ $boxes }} BULTOS)
                    </div>
                    <h1 class="text-xl font-extrabold text-zinc-950 mt-0.5">
                        {{ $product->sku }} · {{ $product->name }}
                    </h1>
                    <div class="mono text-xs text-zinc-600 mt-1">
                        FOLIO: <strong>{{ $folio }}</strong> · ANDÉN: <strong>{{ $dock }}</strong> · PROVEEDOR: {{ $supplier }}
                    </div>
                </div>
                <div class="text-right mono">
                    <div class="text-[10px] text-zinc-500 uppercase">SUCURSAL DESTINO DE ENTREGA</div>
                    <div class="text-2xl font-extrabold text-zinc-950">{{ $branch->code }}</div>
                    <div class="text-xs font-bold text-orange-600">{{ $branch->name }} ({{ $branch->rack_code }})</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center mono text-xs">
                <div class="md:col-span-3 border-2 border-zinc-950 p-3 text-center bg-white">
                    <img src="{{ $batchQrDataUri }}" alt="QR Lote {{ $product->sku }}" class="w-36 h-36 mx-auto">
                    <div class="text-[10px] font-bold mt-1">QR LOTE ({{ $boxes }} CAJAS MÁSTER)</div>
                </div>

                <div class="md:col-span-9 space-y-2">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 bg-zinc-50 border border-zinc-300 p-3">
                        <div>
                            <span class="text-[10px] text-zinc-500 block">ZONA ESTIBA</span>
                            <strong>{{ $product->zone_priority }} · {{ $product->stow_zone }}</strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">PESO BRUTO LOTE</span>
                            <strong>{{ number_format($boxes * $product->master_weight_kg, 2) }} kg ({{ $product->master_weight_kg }} kg/cja)</strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">DENSIDAD / ECT</span>
                            <strong>{{ $product->master_density_kg_m3 }} kg/m³ · {{ $product->ect_rating }} ECT</strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-500 block">CAMAS MÁX / FACTOR</span>
                            <strong>{{ $product->max_layers_operational }} camas · ×{{ $product->master_factor }} uds</strong>
                        </div>
                    </div>

                    <div class="p-2.5 rounded bg-zinc-950 text-white text-[11px] break-all">
                        <span class="text-orange-400 font-bold">JSON QR:</span> {{ $batchPayload }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Grilla de Etiquetas Individuales por Caja Máster -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($labels as $lbl)
                <div class="bg-white border-2 border-zinc-950 rounded-md p-4 mono">
                    <div class="flex items-center justify-between border-b-2 border-zinc-950 pb-2 mb-3">
                        <div>
                            <div class="text-[9px] font-bold uppercase text-zinc-500">CEDIS CDMX · ETIQUETA INDIVIDUAL DE BULTO</div>
                            <div class="text-sm font-extrabold text-zinc-950">{{ $lbl['bulto_code'] }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-[9px] text-zinc-500">DESTINO {{ $branch->name }}</div>
                            <div class="text-lg font-extrabold text-zinc-950">{{ $branch->code }} · {{ $branch->rack_code }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3 items-center text-[11px]">
                        <div class="col-span-1 border border-zinc-950 p-1.5 text-center">
                            <img src="{{ $lbl['qr_data_uri'] }}" alt="QR {{ $lbl['bulto_code'] }}" class="w-28 h-28 mx-auto">
                            <span class="text-[9px] font-bold">CAJA {{ $lbl['index'] }} / {{ $lbl['total'] }}</span>
                        </div>
                        <div class="col-span-2 space-y-1">
                            <div><strong>SKU:</strong> {{ $product->sku }}</div>
                            <div class="font-sans text-xs font-semibold text-zinc-800 truncate">{{ $product->name }}</div>
                            <div><strong>ZONA:</strong> {{ $product->stow_zone }}</div>
                            <div><strong>PESO / VOL:</strong> {{ $product->master_weight_kg }} kg · {{ $product->master_volume_m3 }} m³</div>
                            <div><strong>PIEZAS:</strong> {{ $product->master_factor }} ({{ $product->commercial_presentation }})</div>
                            <div><strong>ORIENTACIÓN:</strong> {{ $product->orientation_rule }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</body>
</html>
