<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiquetas Integradas de Proveedor · {{ $folio }} — CEDIS CDMX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .mono { font-family: 'JetBrains Mono', monospace; font-variant-numeric: tabular-nums; }
        .box-card { page-break-inside: avoid; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; padding: 0 !important; }
        }
    </style>
</head>
<body class="bg-zinc-100 text-zinc-950 py-6 px-4">
    <div class="max-w-5xl mx-auto space-y-6">
        <!-- Barra superior no imprimible -->
        <div class="no-print bg-white border border-zinc-300 rounded-md p-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                @if(Auth::check() && Auth::user()->isProveedor())
                    <a href="{{ route('dashboard.proveedor') }}"
                       class="text-xs font-bold uppercase text-zinc-700 hover:text-zinc-950">
                        ← Volver al Portal de Proveedores (CEDIS CDMX)
                    </a>
                @else
                    <a href="{{ route('dashboard.recepcion') }}"
                       class="text-xs font-bold uppercase text-zinc-700 hover:text-zinc-950">
                        ← Volver a Recepción QR del Operador (CEDIS CDMX)
                    </a>
                @endif
                <p class="text-xs text-zinc-500 mt-0.5">
                    <strong>Planilla de Proveedor:</strong> 1 Etiqueta Principal del Conjunto ({{ count($summaryRows) }} artículo(s) · {{ $boxes }} cajas) + <strong>{{ count($labels) }} Etiquetas Individuales</strong> (1 por cada caja).
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($hasMoreBoxes)
                    <a href="{{ request()->fullUrlWithQuery(['all_boxes' => 1]) }}"
                       class="px-3 py-2 rounded border border-zinc-950 bg-white hover:bg-zinc-100 text-zinc-950 text-xs font-bold uppercase">
                        Cargar todas las {{ $boxes }} cajas
                    </a>
                @endif
                <button type="button" onclick="window.print()"
                        class="px-4 py-2 rounded bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold uppercase cursor-pointer">
                    Imprimir Conjunto + Etiquetas por Caja
                </button>
            </div>
        </div>

        <!-- 1. ETIQUETA PRINCIPAL DEL CONJUNTO (TODOS LOS ARTÍCULOS) -->
        <div class="box-card bg-white border-2 border-zinc-950 rounded-md overflow-hidden">
            <div class="bg-zinc-950 text-white px-6 py-3.5 flex flex-col md:flex-row md:items-center justify-between gap-2 mono">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-widest text-orange-400">
                        ETIQUETA INTEGRADA DE PROVEEDOR · ETIQUETA PRINCIPAL DEL CONJUNTO ({{ $boxes }} CAJAS)
                    </div>
                    <h1 class="text-lg sm:text-xl font-extrabold uppercase mt-0.5">
                        {{ $folio }} · {{ $supplier }}
                    </h1>
                </div>
                <div class="text-right text-xs">
                    <span class="px-2.5 py-0.5 bg-orange-500 text-zinc-950 font-extrabold uppercase">ANDÉN CITA: {{ $dock }}</span>
                    <div class="text-[10px] text-zinc-300 mt-1">{{ $etaWindow }}</div>
                </div>
            </div>

            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center mono text-xs">
                    <div class="md:col-span-3 border-2 border-zinc-950 p-3 text-center bg-white">
                        <img src="{{ $batchQrDataUri }}" alt="QR Principal del Conjunto" class="w-40 h-40 mx-auto" style="image-rendering: pixelated;">
                        <div class="text-[10px] font-bold mt-1 uppercase">QR PRINCIPAL CONJUNTO</div>
                        <div class="text-[9px] text-zinc-500">{{ $boxes }} CAJAS · {{ count($summaryRows) }} SKU(S)</div>
                    </div>

                    <div class="md:col-span-9 space-y-3">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 bg-zinc-50 border border-zinc-300 p-3 text-center">
                            <div>
                                <span class="text-[10px] text-zinc-500 block">ARTÍCULOS / SKUs</span>
                                <strong class="text-sm text-zinc-950">{{ count($summaryRows) }}</strong>
                            </div>
                            <div>
                                <span class="text-[10px] text-zinc-500 block">TOTAL CAJAS MÁSTER</span>
                                <strong class="text-sm text-zinc-950">{{ number_format($boxes) }}</strong>
                            </div>
                            <div>
                                <span class="text-[10px] text-zinc-500 block">TOTAL PIEZAS</span>
                                <strong class="text-sm text-zinc-950">{{ number_format($totalPieces) }}</strong>
                            </div>
                            <div>
                                <span class="text-[10px] text-zinc-500 block">PESO BRUTO CONJUNTO</span>
                                <strong class="text-sm text-zinc-950">{{ number_format($totalWeightKg, 2) }} kg</strong>
                            </div>
                        </div>

                        <!-- Tabla resumen de todos los artículos del conjunto -->
                        <table class="w-full text-left text-[11px] border-collapse border border-zinc-300">
                            <thead>
                                <tr class="bg-zinc-950 text-white text-[9px] uppercase">
                                    <th class="py-1.5 px-2.5">SKU</th>
                                    <th class="py-1.5 px-2.5">Artículo</th>
                                    <th class="py-1.5 px-2.5">Destino CDMX</th>
                                    <th class="py-1.5 px-2.5 text-right">Cajas</th>
                                    <th class="py-1.5 px-2.5 text-right">Peso</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200">
                                @foreach($summaryRows as $row)
                                    <tr class="even:bg-zinc-50">
                                        <td class="py-1.5 px-2.5 font-extrabold">{{ $row['sku'] }}</td>
                                        <td class="py-1.5 px-2.5 font-sans">{{ $row['name'] }}</td>
                                        <td class="py-1.5 px-2.5 font-bold">{{ $row['branch_code'] }} · {{ $row['branch_name'] }} ({{ $row['rack_code'] }})</td>
                                        <td class="py-1.5 px-2.5 text-right font-bold">{{ $row['boxes'] }}</td>
                                        <td class="py-1.5 px-2.5 text-right">{{ number_format($row['weight_kg'], 1) }} kg</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Separador de sección -->
        <div class="flex items-center justify-between border-b-2 border-zinc-950 pb-1.5 mono">
            <h2 class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                2. Etiquetas Individuales por Cada Caja ({{ count($labels) }} de {{ $boxes }} cajas)
            </h2>
            <span class="text-[10px] text-zinc-500 uppercase">Adherir 1 etiqueta por caja máster antes de despachar</span>
        </div>

        <!-- 2. GRILLA DE 1 ETIQUETA INDIVIDUAL POR CADA CAJA -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($labels as $lbl)
                <div class="box-card bg-white border-2 border-zinc-950 rounded-md p-4 mono">
                    <div class="flex items-center justify-between border-b-2 border-zinc-950 pb-2 mb-3">
                        <div>
                            <div class="text-[9px] font-bold uppercase text-orange-600">ETIQUETA INDIVIDUAL DE CAJA · PROVEEDOR</div>
                            <div class="text-sm font-extrabold text-zinc-950">{{ $lbl['bulto_code'] }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-[9px] text-zinc-500">ANDÉN {{ $lbl['dock'] }} → {{ $lbl['branch_name'] }}</div>
                            <div class="text-base font-extrabold text-zinc-950">{{ $lbl['branch_code'] }} · {{ $lbl['rack_code'] }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3 items-center text-[11px]">
                        <div class="col-span-1 border border-zinc-950 p-1.5 text-center bg-white">
                            <img src="{{ $lbl['qr_data_uri'] }}" alt="QR {{ $lbl['bulto_code'] }}" class="w-36 h-36 mx-auto" style="image-rendering: pixelated;">
                            <span class="text-[9px] font-bold block mt-1">CAJA {{ $lbl['index'] }} / {{ $lbl['total'] }}</span>
                        </div>
                        <div class="col-span-2 space-y-1">
                            <div><strong>PROVEEDOR:</strong> {{ $lbl['supplier'] }}</div>
                            <div><strong>SKU:</strong> {{ $lbl['sku'] }} (Caja {{ $lbl['item_box_index'] }}/{{ $lbl['item_box_total'] }})</div>
                            <div class="font-sans text-xs font-semibold text-zinc-800 truncate">{{ $lbl['product_name'] }}</div>
                            <div><strong>ZONA:</strong> {{ $lbl['stow_zone'] }}</div>
                            <div><strong>PESO / PIEZAS:</strong> {{ $lbl['master_weight_kg'] }} kg · ×{{ $lbl['master_factor'] }} uds</div>
                            <div><strong>ORIENTACIÓN:</strong> {{ $lbl['orientation_rule'] }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</body>
</html>
