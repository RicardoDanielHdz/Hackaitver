<div x-data="{
        savedTripsPerMonth: 8,
        costPerTripMxn: 4800,
        shrinkageSavingsMxn: 22000,
        overtimeSavingsMxn: 14500,
        capexTotal: {{ $quotation['capex_total_mxn'] }},
        opexMonthly: {{ $quotation['opex_monthly_mxn'] }},
        get grossMonthlySavings() {
            return (this.savedTripsPerMonth * this.costPerTripMxn) + Number(this.shrinkageSavingsMxn) + Number(this.overtimeSavingsMxn);
        },
        get netMonthlyBenefit() {
            return this.grossMonthlySavings - this.opexMonthly;
        },
        get paybackMonths() {
            return this.netMonthlyBenefit > 0 ? (this.capexTotal / this.netMonthlyBenefit).toFixed(1) : 'N/A';
        },
        get annualRoiPct() {
            const annualNet = this.netMonthlyBenefit * 12;
            return Math.round(((annualNet - this.capexTotal) / this.capexTotal) * 100);
        }
     }"
     class="space-y-6">

    <!-- Tarjetas Ejecutivas Financieras -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 font-mono tabular-nums">
        <div class="bg-white border border-zinc-200 border-t-4 border-t-zinc-950 rounded-md p-4">
            <div class="text-[10px] text-zinc-500 uppercase">Inversión Inicial (CAPEX)</div>
            <div class="text-2xl font-extrabold text-zinc-950 mt-1">${{ number_format($quotation['capex_total_mxn']) }} <span class="text-xs font-normal">MXN</span></div>
            <div class="text-[11px] font-sans text-zinc-500 mt-1">Con IVA 16%: ${{ number_format($quotation['capex_with_iva_mxn']) }} MXN</div>
        </div>

        <div class="bg-white border border-zinc-200 border-t-4 border-t-zinc-950 rounded-md p-4">
            <div class="text-[10px] text-zinc-500 uppercase">Costo Recurrente (OPEX)</div>
            <div class="text-2xl font-extrabold text-zinc-950 mt-1">${{ number_format($quotation['opex_monthly_mxn']) }} <span class="text-xs font-normal">/ mes</span></div>
            <div class="text-[11px] font-sans text-zinc-500 mt-1">Papel, tóner láser (520 emb/mes) y red</div>
        </div>

        <div class="bg-white border border-zinc-200 border-t-4 border-t-orange-500 rounded-md p-4">
            <div class="text-[10px] text-orange-600 font-bold uppercase">Beneficio Neto Mensual</div>
            <div class="text-2xl font-extrabold text-zinc-950 mt-1" x-text="'$' + netMonthlyBenefit.toLocaleString('es-MX')"></div>
            <div class="text-[11px] font-sans text-zinc-500 mt-1">Ahorros operativos menos OPEX</div>
        </div>

        <div class="bg-zinc-950 text-white border border-zinc-950 border-t-4 border-t-orange-500 rounded-md p-4">
            <div class="text-[10px] text-orange-400 font-bold uppercase">Recuperación (Payback)</div>
            <div class="text-2xl font-extrabold text-white mt-1"><span x-text="paybackMonths"></span> <span class="text-xs font-normal text-zinc-400">meses</span></div>
            <div class="text-[11px] font-sans text-zinc-300 mt-1">ROI Año 1: <strong class="text-orange-400" x-text="annualRoiPct + '%'"></strong></div>
        </div>
    </div>

    <!-- Desglose de Cotización Real de Mercado (CAPEX) -->
    <div class="bg-white border border-zinc-200 rounded-lg p-5">
        <div class="pb-3 mb-4 border-b border-zinc-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-extrabold uppercase tracking-wider text-zinc-950">
                    Cotización de Equipos e Infraestructura (Mercado Real MXN)
                </h2>
                <p class="text-xs text-zinc-500">Dimensionado para nave de 100×40 m (4,000 m²), techos de 9 m y 32 racks metálicos de 6 m.</p>
            </div>
            <span class="text-[10px] font-mono uppercase px-2.5 py-1 rounded bg-zinc-100 border border-zinc-300 text-zinc-800">
                Precios antes de IVA
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-zinc-950 text-white font-mono text-[10px] uppercase">
                        <th class="py-2.5 px-3">Rubro Requerido</th>
                        <th class="py-2.5 px-3">Especificación Técnica y Modelo Comercial</th>
                        <th class="py-2.5 px-3">Cantidad</th>
                        <th class="py-2.5 px-3 text-right">Unitario (MXN)</th>
                        <th class="py-2.5 px-3 text-right">Subtotal (MXN)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    @foreach($quotation['capex_items'] as $item)
                        <tr class="even:bg-zinc-50/80 hover:bg-orange-50/40 transition">
                            <td class="py-3 px-3 font-bold text-zinc-950">{{ $item['category'] }}</td>
                            <td class="py-3 px-3 text-zinc-600 max-w-md">{{ $item['spec'] }}</td>
                            <td class="py-3 px-3 font-mono text-zinc-800">{{ $item['qty'] }}</td>
                            <td class="py-3 px-3 font-mono tabular-nums text-right text-zinc-700">${{ number_format($item['unit_mxn']) }}</td>
                            <td class="py-3 px-3 font-mono tabular-nums font-bold text-right text-zinc-950">${{ number_format($item['subtotal_mxn']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Consumibles Mensuales (OPEX) + Simulador Interactivo de ROI -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Proyección Mensual de Papel y Tóner -->
        <div class="lg:col-span-6 bg-white border border-zinc-200 rounded-lg p-5">
            <h3 class="text-xs font-extrabold uppercase tracking-wider text-zinc-950 pb-3 mb-3 border-b border-zinc-200">
                Proyección Mensual de Consumibles y Servicios (OPEX)
            </h3>
            <div class="divide-y divide-zinc-200">
                @foreach($quotation['opex_items'] as $opex)
                    <div class="py-3 flex items-start justify-between gap-4 text-xs">
                        <div>
                            <div class="font-bold text-zinc-950">{{ $opex['concept'] }}</div>
                            <p class="text-[11px] text-zinc-500 mt-0.5">{{ $opex['calculation'] }}</p>
                        </div>
                        <span class="font-mono tabular-nums font-bold text-zinc-950 shrink-0">
                            ${{ number_format($opex['monthly_mxn']) }}/mes
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Simulador de Viabilidad para el Jurado -->
        <div class="lg:col-span-6 bg-zinc-950 text-white rounded-lg p-5">
            <div class="pb-3 mb-4 border-b border-zinc-800 flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-mono uppercase text-orange-400 font-bold">SIMULADOR DE SENSIBILIDAD</span>
                    <h3 class="text-sm font-extrabold text-white">Impacto del Candado &ge;90% en Tortón</h3>
                </div>
                <span class="font-mono text-xs text-orange-400 font-bold" x-text="'+$' + grossMonthlySavings.toLocaleString('es-MX') + '/mes'"></span>
            </div>

            <div class="space-y-4 text-xs">
                <div>
                    <div class="flex justify-between mb-1 font-mono">
                        <span class="text-zinc-300">Tortones subutilizados evitados al mes:</span>
                        <strong class="text-orange-400" x-text="savedTripsPerMonth + ' viajes ($' + (savedTripsPerMonth * costPerTripMxn).toLocaleString('es-MX') + ')'"></strong>
                    </div>
                    <input type="range" min="2" max="20" x-model="savedTripsPerMonth" class="w-full accent-orange-500 cursor-pointer">
                </div>

                <div>
                    <div class="flex justify-between mb-1 font-mono">
                        <span class="text-zinc-300">Reducción mensual de faltantes/errores QR:</span>
                        <strong class="text-white" x-text="'$' + Number(shrinkageSavingsMxn).toLocaleString('es-MX') + ' MXN'"></strong>
                    </div>
                    <input type="range" min="5000" max="45000" step="1000" x-model="shrinkageSavingsMxn" class="w-full accent-orange-500 cursor-pointer">
                </div>

                <div class="pt-3 border-t border-zinc-800 flex items-center justify-between text-[11px] font-mono text-zinc-400">
                    <span>Ahorro por citas sin estadías en andén: $14,500/mes</span>
                    <span class="text-orange-400 font-bold" x-text="'Payback: ' + paybackMonths + ' meses'"></span>
                </div>
            </div>
        </div>
    </div>
</div>
