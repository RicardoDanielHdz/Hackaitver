<div class="space-y-6">
    <!-- Plano Arquitectónico 2D de la Nave (100 m × 40 m) -->
    <div class="bg-white border border-zinc-200 rounded-lg p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 mb-4 border-b border-zinc-200">
            <div>
                <span class="text-[10px] font-mono font-bold uppercase tracking-widest text-orange-600">GEMELO DIGITAL DE ALMACÉN</span>
                <h2 class="text-base font-extrabold text-zinc-950">Plano de Nave CEDIS (100 m × 40 m · Altura 9 m · Racks 6 m)</h2>
            </div>
            <div class="text-xs font-mono text-zinc-600">
                Selecciona cualquiera de los 32 Racks para abrir su elevación frontal (4×8)
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-stretch bg-zinc-100 border border-zinc-300 rounded-md p-4 font-mono tabular-nums">
            <!-- Muro Oeste: 4 Andenes de Recibo -->
            <div class="lg:col-span-2 flex flex-col justify-between bg-white border border-zinc-300 rounded p-3">
                <div class="text-[10px] font-bold uppercase text-zinc-500 border-b border-zinc-200 pb-1.5 mb-2">
                    MURO OESTE · RECIBO
                </div>
                <div class="space-y-2 my-auto">
                    @foreach(['R1', 'R2', 'R3', 'R4'] as $rDock)
                        <div class="p-2 rounded border {{ in_array($rDock, ['R1', 'R2'], true) ? 'bg-zinc-950 text-white border-zinc-950' : 'bg-zinc-50 text-zinc-700 border-zinc-300' }} text-xs flex items-center justify-between">
                            <span class="font-bold">{{ $rDock }}</span>
                            <span class="text-[10px] {{ in_array($rDock, ['R1', 'R2'], true) ? 'text-orange-400' : 'text-zinc-400' }}">
                                {{ in_array($rDock, ['R1', 'R2'], true) ? 'ACTIVO' : 'LIBRE' }}
                            </span>
                        </div>
                    @endforeach
                </div>
                <div class="text-[10px] text-zinc-400 pt-2 border-t border-zinc-200 mt-2">40 m Frente</div>
            </div>

            <!-- Zona Central: 32 Racks Unilaterales (4 Pasillos × 8 Racks) -->
            <div class="lg:col-span-8 bg-white border border-zinc-300 rounded p-3 flex flex-col justify-between">
                <div class="flex items-center justify-between text-[10px] font-bold uppercase text-zinc-500 border-b border-zinc-200 pb-1.5 mb-3">
                    <span>ZONA DE ALMACENAMIENTO TEMPORAL · 32 RACKS EXCLUSIVOS POR DESTINO</span>
                    <span>100 m Largo</span>
                </div>

                <div class="grid grid-cols-4 sm:grid-cols-8 gap-2">
                    @foreach($branches as $idx => $b)
                        <button type="button"
                                @click="selectedBranchIndex = {{ $idx }}"
                                :class="selectedBranchIndex === {{ $idx }}
                                    ? 'bg-orange-500 text-white border-orange-600 ring-2 ring-orange-500/40'
                                    : ({{ $b['ready_for_torton'] ? 'true' : 'false' }} ? 'bg-zinc-950 text-white border-zinc-950' : 'bg-zinc-50 text-zinc-900 border-zinc-300 hover:border-zinc-950')"
                                class="p-2 rounded border text-left transition cursor-pointer">
                            <div class="text-[10px] font-bold">{{ $b['rack_code'] }}</div>
                            <div class="text-[9px] opacity-80">{{ $b['code'] }}</div>
                            <div class="text-[10px] font-bold mt-1">{{ $b['pallets'] }}/32</div>
                        </button>
                    @endforeach
                </div>

                <div class="flex items-center justify-between text-[10px] text-zinc-500 pt-2 border-t border-zinc-200 mt-3">
                    <span>Tarima estándar: 1.12 × 1.15 m · Máx 1.85 m · 1,000 kg</span>
                    <span>Capacidad total: 1,024 tarimas</span>
                </div>
            </div>

            <!-- Muro Este: 3 Andenes de Embarque -->
            <div class="lg:col-span-2 flex flex-col justify-between bg-white border border-zinc-300 rounded p-3">
                <div class="text-[10px] font-bold uppercase text-zinc-500 border-b border-zinc-200 pb-1.5 mb-2">
                    MURO ESTE · SALIDA
                </div>
                <div class="space-y-2.5 my-auto">
                    @foreach(['E1', 'E2', 'E3'] as $eDock)
                        <div class="p-2.5 rounded border {{ $eDock !== 'E3' ? 'bg-orange-500 text-white border-orange-600' : 'bg-zinc-50 text-zinc-700 border-zinc-300' }} text-xs flex items-center justify-between">
                            <span class="font-bold">{{ $eDock }}</span>
                            <span class="text-[10px] font-bold">{{ $eDock !== 'E3' ? 'TORTÓN' : 'LIBRE' }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="text-[10px] text-zinc-400 pt-2 border-t border-zinc-200 mt-2">Hacia 32 tiendas</div>
            </div>
        </div>
    </div>

    <!-- Inspector de Elevación Frontal: 4 Niveles × 8 Posiciones (32 Tarimas) -->
    <div class="bg-zinc-950 text-white border border-zinc-800 rounded-lg p-6 font-mono tabular-nums">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 mb-5 border-b border-zinc-800">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="px-2 py-0.5 rounded bg-orange-500 text-zinc-950 text-xs font-extrabold" x-text="activeBranch.rack_code"></span>
                    <h3 class="text-lg font-extrabold font-sans text-white" x-text="activeBranch.code + ' · ' + activeBranch.name"></h3>
                </div>
                <p class="text-xs font-sans text-zinc-400 mt-1">
                    Vista frontal unilateral: 4 Niveles de altura (6.0 m) × 8 Posiciones longitudinales = 32 ubicaciones de tarima.
                </p>
            </div>

            <div class="flex flex-wrap gap-3 text-xs">
                <div class="px-3 py-1.5 rounded bg-zinc-900 border border-zinc-800">
                    <span class="text-zinc-400">Tarimas en Rack:</span>
                    <strong class="text-white ml-1" x-text="activeBranch.pallets + ' / 32'"></strong>
                </div>
                <div class="px-3 py-1.5 rounded bg-zinc-900 border border-zinc-800">
                    <span class="text-zinc-400">Peso Tortón:</span>
                    <strong :class="activeBranch.weight_pct >= 90 ? 'text-orange-400' : 'text-white'" class="ml-1" x-text="activeBranch.weight_pct + '%'"></strong>
                </div>
                <div class="px-3 py-1.5 rounded bg-zinc-900 border border-zinc-800">
                    <span class="text-zinc-400">Volumen Tortón:</span>
                    <strong :class="activeBranch.volume_pct >= 90 ? 'text-orange-400' : 'text-white'" class="ml-1" x-text="activeBranch.volume_pct + '%'"></strong>
                </div>
            </div>
        </div>

        <!-- Grilla 4 Niveles (de Nivel 4 arriba a Nivel 1 piso) × 8 Posiciones -->
        <div class="space-y-2.5 overflow-x-auto">
            <template x-for="level in [4, 3, 2, 1]" :key="level">
                <div class="grid grid-cols-9 gap-2 min-w-[680px] items-center">
                    <div class="col-span-1 text-xs font-bold text-zinc-400 pr-2 border-r border-zinc-800">
                        NIVEL <span class="text-white" x-text="level"></span>
                        <div class="text-[10px] text-zinc-500" x-text="level === 1 ? 'Piso pesado' : 'Estiba'"></div>
                    </div>

                    <template x-for="pos in [1, 2, 3, 4, 5, 6, 7, 8]" :key="pos">
                        <div class="p-2.5 rounded border text-xs transition"
                             :class="(((level - 1) * 8 + pos) <= activeBranch.pallets)
                                ? 'bg-zinc-900 border-orange-500/80 text-white'
                                : 'bg-zinc-950 border-dashed border-zinc-800 text-zinc-600'">
                            <div class="flex items-center justify-between text-[10px]">
                                <span x-text="'N' + level + '·P' + pos"></span>
                                <span x-show="((level - 1) * 8 + pos) <= activeBranch.pallets" class="w-2 h-2 rounded-full bg-orange-500"></span>
                            </div>
                            <div class="mt-1 font-bold text-[11px]"
                                 x-text="(((level - 1) * 8 + pos) <= activeBranch.pallets) ? (910 + ((pos * 13) % 75)) + ' kg' : 'LIBRE'">
                            </div>
                            <div class="text-[9px] text-zinc-400"
                                 x-text="(((level - 1) * 8 + pos) <= activeBranch.pallets) ? '1.78 m · Film OK' : 'Disponible'">
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>
