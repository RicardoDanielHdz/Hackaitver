<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiqueta Única de Embarque Tortón {{ $order->folio }} — CEDIS CDMX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .mono { font-family: 'JetBrains Mono', monospace; font-variant-numeric: tabular-nums; }
        @page { size: letter portrait; margin: 10mm; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; padding: 0 !important; }
            .single-label { box-shadow: none !important; max-width: 100% !important; page-break-inside: avoid; }
        }
    </style>
</head>
<body class="bg-zinc-100 text-zinc-950 py-6 px-4">
    <div class="max-w-4xl mx-auto space-y-4">
        <!-- Barra no imprimible -->
        <div class="no-print bg-white border border-zinc-300 rounded-md p-3.5 flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('wms.index', ['branch' => $order->branch->code]) }}"
               class="text-xs font-bold uppercase text-zinc-700 hover:text-zinc-950">
                ← Volver al Motor de Acomodo WMS
            </a>
            <button type="button" onclick="window.print()"
                    class="px-4 py-2 rounded bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold uppercase cursor-pointer">
                Imprimir Etiqueta Única de Embarque (PDF)
            </button>
        </div>

        <!-- ETIQUETA ÚNICA DE EMBARQUE FINAL TORTÓN (1 SOLA HOJA · 1 SOLO QR) -->
        <div class="single-label bg-white border-2 border-zinc-950 rounded-xs overflow-hidden shadow-md">
            <!-- Cabecera -->
            <div class="bg-zinc-950 text-white px-5 py-3 flex flex-wrap items-center justify-between gap-2 mono">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-orange-400">
                        ETIQUETA MAESTRA DE EMBARQUE FINAL TORTÓN · CEDIS CDMX
                    </span>
                    <h1 class="text-xl font-extrabold uppercase tracking-tight">
                        {{ $order->folio }} · {{ $order->branch->code }} {{ $order->branch->name }}
                    </h1>
                </div>
                <div class="text-right text-xs">
                    <span class="px-2.5 py-0.5 bg-orange-500 text-zinc-950 font-extrabold uppercase inline-block">
                        MARCHAMO: {{ $totals['seal_code'] }}
                    </span>
                    <div class="text-[10px] text-zinc-300 mt-1">
                        EMISIÓN: {{ $order->dispatched_at?->format('d/m/Y H:i') }}
                    </div>
                </div>
            </div>

            <div class="p-5 space-y-4 mono text-xs">
                <!-- Bloque 1: 1 Único QR + Origen, Destino, Unidad y Totales -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                    <div class="md:col-span-3 border-2 border-zinc-950 p-2 text-center bg-white">
                        <img src="{{ $qrSvgDataUri }}" alt="QR Maestro {{ $order->folio }}" class="w-36 h-36 mx-auto" style="image-rendering: pixelated;">
                        <div class="text-[9px] font-bold mt-1 uppercase">QR ÚNICO DE EMBARQUE</div>
                        <div class="text-[9px] text-zinc-500">{{ $order->folio }}</div>
                    </div>

                    <div class="md:col-span-9 space-y-2.5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <div class="border border-zinc-300 bg-zinc-50 p-2.5">
                                <span class="text-[9px] font-bold uppercase text-zinc-500 block">ORIGEN / SALIDA CEDIS</span>
                                <strong class="font-sans text-sm text-zinc-950">CEDIS CDMX · Andén {{ $order->dock_code }}</strong>
                                <div class="text-[10px] text-zinc-600 mt-0.5">Rack liberado: <strong>{{ $order->branch->rack_code }}</strong> · Autorizó: {{ $order->createdBy?->name ?? 'Sistema WMS' }}</div>
                            </div>

                            <div class="border-2 border-zinc-950 bg-white p-2.5">
                                <span class="text-[9px] font-bold uppercase text-orange-600 block">DESTINO FINAL (SUCURSAL CDMX)</span>
                                <strong class="font-sans text-sm text-zinc-950">{{ $order->branch->code }} · {{ $order->branch->name }}</strong>
                                <div class="text-[10px] text-zinc-700 mt-0.5">Zona {{ $order->branch->zone }} ({{ $order->branch->distance_km }} km) · Placas <strong>{{ $order->unit_plate }}</strong></div>
                                <div class="text-[10px] text-zinc-600">Operador: {{ $order->driver_name }}</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-5 gap-2 bg-zinc-50 border border-zinc-300 p-2.5 text-center">
                            <div>
                                <span class="text-[9px] text-zinc-500 uppercase block">Tarimas</span>
                                <strong class="text-sm text-zinc-950">{{ $order->pallet_count }}/12</strong>
                            </div>
                            <div>
                                <span class="text-[9px] text-zinc-500 uppercase block">Cajas</span>
                                <strong class="text-sm text-zinc-950">{{ number_format($totals['total_boxes']) }}</strong>
                            </div>
                            <div>
                                <span class="text-[9px] text-zinc-500 uppercase block">Piezas</span>
                                <strong class="text-sm text-zinc-950">{{ number_format($totals['total_pieces']) }}</strong>
                            </div>
                            <div>
                                <span class="text-[9px] text-zinc-500 uppercase block">Peso</span>
                                <strong class="text-sm text-zinc-950">{{ number_format($order->total_weight_kg, 0) }} kg</strong>
                                <span class="block text-[9px] text-emerald-700 font-bold">{{ $order->weight_pct }}%</span>
                            </div>
                            <div>
                                <span class="text-[9px] text-zinc-500 uppercase block">Volumen</span>
                                <strong class="text-sm text-zinc-950">{{ number_format($order->total_volume_m3, 2) }} m³</strong>
                                <span class="block text-[9px] text-emerald-700 font-bold">{{ $order->volume_pct }}%</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2 text-[10px]">
                            <div class="flex flex-wrap gap-1.5">
                                <span class="px-2 py-0.5 bg-zinc-950 text-white font-bold uppercase">{{ $totals['distinct_skus'] }} SKUs</span>
                                @if($totals['has_liquids'])
                                    <span class="px-2 py-0.5 bg-red-600 text-white font-bold uppercase">⚠ CONTIENE LÍQUIDOS (EN PISO)</span>
                                @endif
                                @if($totals['has_hygroscopic'])
                                    <span class="px-2 py-0.5 bg-zinc-100 border border-zinc-950 text-zinc-950 font-bold uppercase">PAPEL · MANTENER SECO</span>
                                @endif
                            </div>
                            <span class="font-sans text-zinc-600 truncate max-w-xs">
                                <strong>Orígenes:</strong> {{ $totals['suppliers_list'] ?: 'CEDIS CDMX' }}
                            </span>
                        </div>
                    </div>
                </div>

                @if($order->exception_authorized)
                    <div class="p-2.5 bg-zinc-950 text-white border-l-4 border-orange-500 text-[11px]">
                        <strong class="text-orange-400">EXCEPCIÓN GERENCIAL (&lt;90%):</strong> {{ $order->exception_reason }}
                    </div>
                @endif

                <!-- Bloque 2: Desglose Consolidado del Contenido (Compacto dentro de la misma etiqueta) -->
                <div>
                    <div class="text-[10px] font-extrabold uppercase tracking-wider text-zinc-950 mb-1.5">
                        Desglose Consolidado del Contenido Embarcado
                    </div>
                    <table class="w-full text-left text-[11px] border-collapse border border-zinc-300">
                        <thead>
                            <tr class="bg-zinc-950 text-white text-[9px] uppercase">
                                <th class="py-1.5 px-2">SKU</th>
                                <th class="py-1.5 px-2">Descripción</th>
                                <th class="py-1.5 px-2">Zona</th>
                                <th class="py-1.5 px-2 text-right">Cajas</th>
                                <th class="py-1.5 px-2 text-right">Piezas</th>
                                <th class="py-1.5 px-2 text-right">Peso</th>
                                <th class="py-1.5 px-2">Tarimas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            @foreach($skuSummary as $row)
                                <tr class="even:bg-zinc-50/80">
                                    <td class="py-1.5 px-2 font-extrabold whitespace-nowrap">{{ $row['sku'] }}</td>
                                    <td class="py-1.5 px-2 font-sans font-semibold text-zinc-900">{{ $row['name'] }}</td>
                                    <td class="py-1.5 px-2 text-[10px]">
                                        {{ $row['stow_zone'] }}
                                        @if($row['is_liquid']) <span class="text-red-700 font-bold">(LÍQ)</span> @endif
                                    </td>
                                    <td class="py-1.5 px-2 text-right font-bold">{{ number_format($row['boxes']) }}</td>
                                    <td class="py-1.5 px-2 text-right">{{ number_format($row['pieces']) }}</td>
                                    <td class="py-1.5 px-2 text-right font-bold">{{ number_format($row['weight_kg'], 1) }} kg</td>
                                    <td class="py-1.5 px-2 text-[9px] text-zinc-600">{{ $row['pallets_list'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Bloque 3: Distribución Compacta por Ejes Tortón (T-01..T-12) -->
                <div class="grid grid-cols-3 gap-2 text-[10px] text-center bg-zinc-50 border border-zinc-300 p-2">
                    <div class="bg-white border border-zinc-200 p-1.5">
                        <span class="text-zinc-500 block">EJE DELANTERO</span>
                        <strong class="text-zinc-950">T-01 a T-04 (Base / Pesado)</strong>
                    </div>
                    <div class="bg-white border border-zinc-200 p-1.5">
                        <span class="text-zinc-500 block">EJE CENTRAL</span>
                        <strong class="text-zinc-950">T-05 a T-08 (Intermedio)</strong>
                    </div>
                    <div class="bg-white border border-zinc-200 p-1.5">
                        <span class="text-zinc-500 block">EJE TRASERO</span>
                        <strong class="text-zinc-950">T-09 a T-12 (Descarga 1ª)</strong>
                    </div>
                </div>

                <!-- Bloque 4: Firmas de Acuse -->
                <div class="grid grid-cols-3 gap-6 pt-5 text-center text-[10px]">
                    <div class="border-t border-zinc-950 pt-1.5">
                        JEFE DE EMBARQUE CEDIS
                        <span class="block text-[9px] text-zinc-500">Andén {{ $order->dock_code }} · {{ $totals['seal_code'] }}</span>
                    </div>
                    <div class="border-t border-zinc-950 pt-1.5">
                        OPERADOR TORTÓN ({{ $order->unit_plate }})
                        <span class="block text-[9px] text-zinc-500">{{ $order->driver_name }}</span>
                    </div>
                    <div class="border-t border-zinc-950 pt-1.5">
                        RECEPTOR {{ $order->branch->code }}
                        <span class="block text-[9px] text-zinc-500">Recibí {{ $order->pallet_count }} tar / {{ number_format($totals['total_boxes']) }} cjas</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
