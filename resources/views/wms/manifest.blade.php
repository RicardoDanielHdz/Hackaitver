<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden de Salida {{ $order->folio }} — CEDIS CDMX</title>
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
    <div class="max-w-4xl mx-auto bg-white border-2 border-zinc-950 p-6 sm:p-8 space-y-6">
        <!-- Barra no imprimible -->
        <div class="no-print flex items-center justify-between pb-4 border-b border-zinc-200">
            <a href="{{ route('wms.index', ['branch' => $order->branch->code]) }}"
               class="text-xs font-bold uppercase text-zinc-700 hover:text-zinc-950">
                ← Volver al Motor de Acomodo WMS
            </a>
            <button type="button" onclick="window.print()"
                    class="px-4 py-2 rounded bg-orange-500 hover:bg-orange-600 text-white text-xs font-bold uppercase cursor-pointer">
                Imprimir / Guardar PDF
            </button>
        </div>

        <!-- Encabezado Oficial -->
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 border-b-2 border-zinc-950 pb-5">
            <div>
                <div class="mono text-[11px] font-bold uppercase tracking-widest text-orange-600">
                    ORDEN DE SALIDA Y EXTRACCIÓN DE RACK · CEDIS PAPELERÍA CDMX
                </div>
                <h1 class="text-2xl font-extrabold uppercase tracking-tight mt-1">
                    Relación de Embarque {{ $order->folio }}
                </h1>
                <div class="mono text-xs text-zinc-700 mt-1">
                    DESTINO: <strong>{{ $order->branch->code }} · {{ $order->branch->name }}</strong> ({{ $order->branch->rack_code }})
                </div>
                <div class="mono text-xs text-zinc-500 mt-0.5">
                    EMISIÓN: {{ $order->dispatched_at?->format('d/m/Y H:i') }} · AUTORIZÓ: {{ $order->createdBy?->name ?? 'Sistema WMS' }}
                </div>
            </div>

            <div class="border-2 border-zinc-950 p-2 text-center shrink-0">
                <img src="{{ $qrSvgDataUri }}" alt="QR Maestro {{ $order->folio }}" class="w-28 h-28 mx-auto">
                <div class="mono text-[9px] font-bold mt-1">QR MAESTRO SUCURSAL</div>
            </div>
        </div>

        <!-- Datos de Unidad y Verificación Dual Tortón -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mono text-xs bg-zinc-50 border border-zinc-300 p-4">
            <div>
                <div class="text-[10px] text-zinc-500 uppercase">Andén Salida</div>
                <div class="font-bold text-base">{{ $order->dock_code }}</div>
            </div>
            <div>
                <div class="text-[10px] text-zinc-500 uppercase">Placas Tortón</div>
                <div class="font-bold text-base">{{ $order->unit_plate }}</div>
            </div>
            <div>
                <div class="text-[10px] text-zinc-500 uppercase">Peso Consolidado</div>
                <div class="font-bold text-base">{{ number_format($order->total_weight_kg, 1) }} kg ({{ $order->weight_pct }}%)</div>
            </div>
            <div>
                <div class="text-[10px] text-zinc-500 uppercase">Volumen Envolvente</div>
                <div class="font-bold text-base">{{ number_format($order->total_volume_m3, 2) }} m³ ({{ $order->volume_pct }}%)</div>
            </div>
            <div class="col-span-2 sm:col-span-4 pt-2 border-t border-zinc-200 flex flex-wrap justify-between gap-2">
                <span><strong>OPERADOR TRANSPORTE:</strong> {{ $order->driver_name }}</span>
                <span><strong>TARIMAS:</strong> {{ $order->pallet_count }} / 12</span>
            </div>
        </div>

        @if($order->exception_authorized)
            <div class="p-3 bg-zinc-950 text-white border-l-4 border-orange-500 text-xs">
                <strong class="mono text-orange-400">EXCEPCIÓN GERENCIAL AUDITADA (&lt;90%):</strong>
                {{ $order->exception_reason }}
            </div>
        @endif

        <!-- Secuencia Numerada de Extracción de Rack y Carga en Tortón -->
        <div>
            <h2 class="text-xs font-extrabold uppercase tracking-wider text-zinc-950 mb-2">
                Plan de Extracción (Rack 4×8 → Plataforma Tortón Balanceada por Ejes)
            </h2>
            <table class="w-full text-left text-xs border-collapse mono">
                <thead>
                    <tr class="bg-zinc-950 text-white text-[10px] uppercase">
                        <th class="py-2 px-2.5">Paso</th>
                        <th class="py-2 px-2.5">Tarima</th>
                        <th class="py-2 px-2.5">Origen Rack</th>
                        <th class="py-2 px-2.5">Posición Tortón</th>
                        <th class="py-2 px-2.5">Peso / Vol</th>
                        <th class="py-2 px-2.5">Contenido SKUs</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 border border-zinc-300">
                    @foreach(($order->loading_sequence ?? []) as $step)
                        <tr>
                            <td class="py-2 px-2.5 font-bold">#{{ $step['step'] }}</td>
                            <td class="py-2 px-2.5 font-bold">{{ $step['pallet_code'] }}</td>
                            <td class="py-2 px-2.5">{{ $step['from_rack'] }}</td>
                            <td class="py-2 px-2.5">
                                <span class="font-bold text-orange-600">{{ $step['truck_slot_label'] }}</span>
                                <span class="block text-[10px] text-zinc-500 font-sans">{{ $step['axle_zone'] }}</span>
                            </td>
                            <td class="py-2 px-2.5">
                                {{ number_format($step['weight_kg'], 1) }} kg
                                <span class="block text-[10px] text-zinc-500">{{ number_format($step['volume_m3'], 2) }} m³</span>
                            </td>
                            <td class="py-2 px-2.5 font-sans text-[11px] text-zinc-700">
                                {{ $step['skus_summary'] }}
                                @if(!empty($step['has_liquids']))
                                    <span class="mono text-[9px] font-bold px-1.5 py-0.5 bg-zinc-950 text-orange-400 rounded ml-1">LÍQUIDOS</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Firmas -->
        <div class="grid grid-cols-3 gap-6 pt-8 text-center text-[11px] mono">
            <div class="border-t border-zinc-950 pt-2">JEFE DE EMBARQUE CEDIS</div>
            <div class="border-t border-zinc-950 pt-2">OPERADOR TORTÓN ({{ $order->unit_plate }})</div>
            <div class="border-t border-zinc-950 pt-2">RECEPTOR {{ $order->branch->code }}</div>
        </div>
    </div>
</body>
</html>
