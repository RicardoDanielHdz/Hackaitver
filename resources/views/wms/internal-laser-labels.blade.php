<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tira Continua de Etiquetas Láser Internas — Operador Dokra</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .mono { font-family: 'JetBrains Mono', monospace; font-variant-numeric: tabular-nums; }
        .laser-cut {
            border-bottom: 2px dashed #52525b;
            page-break-inside: avoid;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: #ffffff !important; padding: 0 !important; }
            .sheet-wrapper { box-shadow: none !important; border: none !important; max-width: 100% !important; }
        }
    </style>
</head>
<body class="bg-zinc-200 text-zinc-950 py-6 px-4">
    <div class="max-w-3xl mx-auto space-y-4">
        <!-- Barra de Control de Impresión Continua -->
        <div class="no-print bg-white border border-zinc-300 rounded-lg p-4 flex flex-wrap items-center justify-between gap-3 shadow-2xs">
            <div>
                <a href="{{ route('dashboard.recepcion') }}" class="text-xs font-bold uppercase text-zinc-700 hover:text-zinc-950">
                    ← Volver a Recepción del Operador
                </a>
                <h1 class="text-sm font-extrabold text-zinc-950 mt-0.5">
                    Tira Continua Láser: 1 Etiqueta Principal del Conjunto + {{ $labels->count() }} Etiquetas por Caja
                </h1>
                <p class="text-[11px] text-zinc-500">
                    Formato continuo monocromático para impresora láser de andén (Acomodo + Origen + Salida).
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($hasMoreBoxes)
                    <a href="{{ request()->fullUrlWithQuery(['all_boxes' => 1]) }}"
                       class="px-3 py-2 rounded border border-zinc-950 bg-white hover:bg-zinc-100 text-zinc-950 text-xs font-bold uppercase">
                        Cargar todas ({{ $totalBoxes }} cajas)
                    </a>
                @endif
                <button type="button" onclick="window.print()"
                        class="px-4 py-2 rounded bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold uppercase cursor-pointer">
                    Imprimir Tira Continua Láser
                </button>
            </div>
        </div>

        <!-- Rollo / Tira Continua de Etiquetas Láser -->
        <div class="sheet-wrapper bg-white border-2 border-zinc-950 shadow-lg">

            @if($masterLabel !== null)
                <!-- 1. ETIQUETA PRINCIPAL DEL CONJUNTO COMPROBADO -->
                <div class="laser-cut p-5 space-y-3 bg-zinc-50">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b-2 border-zinc-950 pb-2 mono">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-widest text-orange-600">
                                Dokra · ETIQUETA PRINCIPAL DEL CONJUNTO COMPROBADO (LÁSER CONTINUO)
                            </div>
                            <div class="text-base font-extrabold text-zinc-950">
                                {{ $masterLabel['code'] }} · {{ $masterLabel['total_boxes'] }} CAJA(S) · {{ number_format($masterLabel['total_weight_kg'], 1) }} kg
                            </div>
                        </div>
                        <span class="px-2.5 py-0.5 bg-zinc-950 text-white text-[10px] font-bold uppercase">
                            RESUMEN DE {{ $masterLabel['items_count'] }} PARTIDA(S)
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center mono text-xs">
                        <div class="sm:col-span-3 border-2 border-zinc-950 p-2 text-center bg-white">
                            <img src="{{ $masterLabel['qr_data_uri'] }}" alt="QR Principal {{ $masterLabel['code'] }}" class="w-28 h-28 mx-auto" style="image-rendering: pixelated;">
                            <span class="text-[9px] font-bold block mt-1">QR CONJUNTO</span>
                        </div>
                        <div class="sm:col-span-9 space-y-2">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[10px]">
                                <div class="border border-zinc-950 p-2 bg-white">
                                    <span class="font-bold text-zinc-500 block uppercase">ORÍGENES COMPROBADOS</span>
                                    <div class="font-sans font-bold text-zinc-950">{{ implode(' · ', $masterLabel['origins']) }}</div>
                                </div>
                                <div class="border border-zinc-950 p-2 bg-white">
                                    <span class="font-bold text-zinc-500 block uppercase">RUTAS DE SALIDA</span>
                                    <div class="font-sans font-bold text-zinc-950">{{ implode(' · ', $masterLabel['destinations']) }}</div>
                                </div>
                            </div>

                            <table class="w-full text-left text-[10px] border-collapse border border-zinc-300 bg-white">
                                <thead>
                                    <tr class="bg-zinc-950 text-white uppercase">
                                        <th class="py-1 px-2">SKU</th>
                                        <th class="py-1 px-2">Cajas</th>
                                        <th class="py-1 px-2">Dónde Acomodar</th>
                                        <th class="py-1 px-2">Salida</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200">
                                    @foreach($masterLabel['summary_rows'] as $row)
                                        <tr>
                                            <td class="py-1 px-2 font-bold">{{ $row['sku'] }}</td>
                                            <td class="py-1 px-2 font-bold">{{ $row['boxes'] }}</td>
                                            <td class="py-1 px-2">{{ $row['rack_coordinate'] }} ({{ $row['pallet_code'] }})</td>
                                            <td class="py-1 px-2">{{ $row['outbound_branch'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <!-- 2. ETIQUETAS INDIVIDUALES POR CADA CAJA (ACOMODO + ORIGEN + SALIDA) -->
            @forelse($labels as $lbl)
                <div class="laser-cut p-5 space-y-3">
                    <!-- Encabezado de la Etiqueta Individual de Caja -->
                    <div class="flex items-center justify-between border-b-2 border-zinc-950 pb-2 mono">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">
                                Dokra · ETIQUETA INTERNA DE ACOMODO Y SALIDA (LÁSER CONTINUO)
                            </div>
                            <div class="text-base font-extrabold text-zinc-950">
                                {{ $lbl['internal_label_code'] }} · {{ $lbl['sku'] }} (CAJA {{ $lbl['index'] }} / {{ $lbl['total'] }} · {{ $lbl['weight_kg'] }} kg)
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="px-2 py-0.5 bg-zinc-950 text-white text-[10px] font-bold uppercase">
                                ✓ VERIFICADO EN ANDÉN {{ $lbl['origin_dock'] }}
                            </span>
                            <div class="text-[10px] text-zinc-500 mt-0.5">{{ $lbl['created_at'] }}</div>
                        </div>
                    </div>

                    <!-- Cuerpo: QR Individual + 1. DÓNDE ACOMODAR + 2. ORIGEN + 3. SALIDA -->
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-stretch mono text-xs">
                        <div class="sm:col-span-3 border-2 border-zinc-950 p-2 flex flex-col items-center justify-center text-center bg-white">
                            <img src="{{ $lbl['qr_data_uri'] }}" alt="QR {{ $lbl['internal_label_code'] }}" class="w-28 h-28" style="image-rendering: pixelated;">
                            <span class="text-[9px] font-bold mt-1">{{ $lbl['internal_label_code'] }}</span>
                        </div>

                        <div class="sm:col-span-9 flex flex-col justify-between space-y-2">
                            <!-- 1. DÓNDE DEBE IR ACOMODADO -->
                            <div class="bg-zinc-950 text-white p-2.5 rounded-xs flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <span class="text-[9px] uppercase text-orange-400 font-bold block">1. UBICACIÓN DE ACOMODO EN ALMACÉN</span>
                                    <strong class="text-sm font-extrabold">{{ $lbl['rack_coordinate'] }}</strong>
                                    <span class="text-[11px] text-zinc-300 ml-1">· Tarima {{ $lbl['pallet_code'] }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="px-2 py-0.5 bg-white text-zinc-950 text-[10px] font-extrabold uppercase">
                                        Piso {{ $lbl['zone_priority'] }} · {{ $lbl['stow_zone'] }} (Cama {{ $lbl['layer_index'] }})
                                    </span>
                                </div>
                            </div>

                            <!-- 2. LUGAR DE ORIGEN y 3. RUTA DE SALIDA -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div class="border border-zinc-950 p-2 bg-zinc-50">
                                    <span class="text-[9px] font-bold uppercase text-zinc-500 block">2. LUGAR DE ORIGEN (ENTRADA)</span>
                                    <div class="font-sans font-bold text-zinc-950 truncate">{{ $lbl['origin_supplier'] }}</div>
                                    <div class="text-[10px] text-zinc-700">Andén {{ $lbl['origin_dock'] }} · {{ $lbl['origin_folio'] }}</div>
                                    <div class="text-[10px] text-zinc-500">QR Prov: {{ $lbl['scanned_bulto_code'] }}</div>
                                </div>

                                <div class="border-2 border-zinc-950 p-2 bg-white">
                                    <span class="text-[9px] font-bold uppercase text-zinc-500 block">3. LUGAR Y RUTA DE SALIDA (EMBARQUE)</span>
                                    <div class="font-sans font-extrabold text-zinc-950">{{ $lbl['outbound_branch'] }}</div>
                                    <div class="text-[10px] font-bold text-zinc-900">{{ $lbl['outbound_dock_target'] }}</div>
                                    <div class="text-[10px] text-zinc-600">{{ $lbl['outbound_cutoff'] }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-[10px] mono text-zinc-600 pt-1">
                        <span>PRODUCTO: <strong>{{ $lbl['product_name'] }}</strong> (Orientación: {{ $lbl['orientation'] }})</span>
                        <span>OPERADOR: {{ $lbl['operator_name'] }}</span>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center mono text-xs text-zinc-500">
                    Aún no hay etiquetas láser generadas. Escanea los códigos QR del proveedor en Recepción para generar la tira continua.
                </div>
            @endforelse
        </div>
    </div>
</body>
</html>
