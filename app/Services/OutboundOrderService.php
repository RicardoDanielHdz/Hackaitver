<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\OutboundOrder;
use App\Models\Pallet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OutboundOrderService
{
    public const float PALLET_FOOTPRINT_AREA_M2 = 1.288; // 1.12 × 1.15 m

    /**
     * Evalúa el estado de consolidación de una sucursal y arma el plan óptimo de extracción
     * y balanceo de ejes para un camión Tortón de 12 tarimas (12,000 kg · 28.59 m³ · candado ≥90%).
     *
     * @return array<string, mixed>
     */
    public function evaluateBranchDispatchPlan(Branch $branch): array
    {
        /** @var Collection<int, Pallet> $slottedPallets */
        $slottedPallets = $branch->pallets()
            ->with(['items.product'])
            ->where('status', 'slotted')
            ->orderBy('wave_number')
            ->orderByDesc('rack_position') // Más cercanas al andén Este (P8→P1) primero
            ->orderBy('slotted_at')
            ->get();

        $selected = $this->selectOptimalTortonPallets($slottedPallets);

        $totalWeightKg = 0.0;
        $totalEnvelopeVolumeM3 = 0.0;

        foreach ($selected as $pallet) {
            $totalWeightKg += (float) $pallet->total_weight_kg;
            $totalEnvelopeVolumeM3 += $this->palletEnvelopeVolumeM3($pallet);
        }

        $totalWeightKg = round($totalWeightKg, 1);
        $totalEnvelopeVolumeM3 = round($totalEnvelopeVolumeM3, 2);

        $weightPct = round(($totalWeightKg / OutboundOrder::TORTON_MAX_WEIGHT_KG) * 100.0, 1);
        $volumePct = round(($totalEnvelopeVolumeM3 / OutboundOrder::TORTON_MAX_VOLUME_M3) * 100.0, 1);

        $missingWeightKg = max(0.0, round(OutboundOrder::TORTON_MIN_WEIGHT_KG - $totalWeightKg, 1));
        $missingVolumeM3 = max(0.0, round(OutboundOrder::TORTON_MIN_VOLUME_M3 - $totalEnvelopeVolumeM3, 2));

        $readyForTorton = $weightPct >= OutboundOrder::TORTON_THRESHOLD_PCT
            && $volumePct >= OutboundOrder::TORTON_THRESHOLD_PCT
            && $selected->count() > 0;

        $loadingPlan = $this->buildAxleBalancedLoadingSequence($branch, $selected);

        return [
            'branch_id' => $branch->id,
            'branch_code' => $branch->code,
            'branch_name' => $branch->name,
            'rack_code' => $branch->rack_code,
            'slotted_total_count' => $slottedPallets->count(),
            'selected_pallet_count' => $selected->count(),
            'total_weight_kg' => $totalWeightKg,
            'weight_pct' => $weightPct,
            'missing_weight_kg' => $missingWeightKg,
            'total_volume_m3' => $totalEnvelopeVolumeM3,
            'volume_pct' => $volumePct,
            'missing_volume_m3' => $missingVolumeM3,
            'ready_for_torton' => $readyForTorton,
            'left_side_weight_kg' => $loadingPlan['left_weight_kg'],
            'right_side_weight_kg' => $loadingPlan['right_weight_kg'],
            'axle_imbalance_pct' => $loadingPlan['imbalance_pct'],
            'loading_sequence' => $loadingPlan['sequence'],
            'selected_pallet_ids' => $selected->pluck('id')->values()->all(),
        ];
    }

    /**
     * Genera y despacha una Orden de Salida real, liberando las coordenadas N×P del Rack 4×8
     * y promoviendo las tarimas de Ola 2 a Ola 1 para el siguiente embarque.
     */
    public function dispatchOutboundOrder(
        Branch $branch,
        string $dockCode,
        string $unitPlate,
        string $driverName,
        bool $exceptionAuthorized = false,
        ?string $exceptionReason = null,
        ?int $userId = null
    ): OutboundOrder {
        $plan = $this->evaluateBranchDispatchPlan($branch);

        if ($plan['selected_pallet_count'] === 0) {
            throw new InvalidArgumentException("La sucursal {$branch->code} no tiene tarimas ubicadas en {$branch->rack_code} para despachar.");
        }

        if (! $plan['ready_for_torton'] && ! $exceptionAuthorized) {
            throw new InvalidArgumentException(sprintf(
                'Candado ≥90%% activo: %s tiene %.1f%% en peso (faltan %.1f kg) y %.1f%% en volumen (faltan %.2f m³). Requiere autorización gerencial de excepción.',
                $branch->code,
                $plan['weight_pct'],
                $plan['missing_weight_kg'],
                $plan['volume_pct'],
                $plan['missing_volume_m3']
            ));
        }

        if (! $plan['ready_for_torton'] && $exceptionAuthorized && trim((string) $exceptionReason) === '') {
            throw new InvalidArgumentException('Debes indicar el motivo auditable de la excepción gerencial (<90%).');
        }

        return DB::transaction(function () use (
            $branch,
            $dockCode,
            $unitPlate,
            $driverName,
            $exceptionAuthorized,
            $exceptionReason,
            $userId,
            $plan
        ): OutboundOrder {
            $nextNumber = ((int) OutboundOrder::query()->max('id')) + 1;
            $folio = sprintf('SAL-2026-%03d', $nextNumber);
            while (OutboundOrder::query()->where('folio', $folio)->exists()) {
                $nextNumber++;
                $folio = sprintf('SAL-2026-%03d', $nextNumber);
            }

            $order = OutboundOrder::create([
                'folio' => $folio,
                'branch_id' => $branch->id,
                'status' => 'dispatched',
                'dock_code' => $dockCode,
                'unit_plate' => strtoupper(trim($unitPlate)),
                'driver_name' => trim($driverName),
                'pallet_count' => $plan['selected_pallet_count'],
                'total_weight_kg' => $plan['total_weight_kg'],
                'weight_pct' => $plan['weight_pct'],
                'total_volume_m3' => $plan['total_volume_m3'],
                'volume_pct' => $plan['volume_pct'],
                'exception_authorized' => $exceptionAuthorized && ! $plan['ready_for_torton'],
                'exception_reason' => ($exceptionAuthorized && ! $plan['ready_for_torton']) ? trim((string) $exceptionReason) : null,
                'loading_sequence' => $plan['loading_sequence'],
                'created_by_user_id' => $userId,
                'dispatched_at' => now(),
            ]);

            foreach ($plan['loading_sequence'] as $step) {
                Pallet::query()->whereKey($step['pallet_id'])->update([
                    'status' => 'dispatched',
                    'outbound_order_id' => $order->id,
                    'truck_slot' => $step['truck_slot'],
                    'rack_level' => null,
                    'rack_position' => null,
                    'dispatched_at' => now(),
                ]);
            }

            // Promover hasta 12 tarimas restantes de Ola 2 hacia Ola 1.
            $remainingIds = $branch->pallets()
                ->whereIn('status', ['slotted', 'building'])
                ->orderBy('wave_number')
                ->orderBy('id')
                ->limit(12)
                ->pluck('id');

            if ($remainingIds->isNotEmpty()) {
                Pallet::query()->whereIn('id', $remainingIds)->update(['wave_number' => 1]);
            }

            return $order->fresh(['branch', 'pallets']);
        });
    }

    /**
     * Volumen envolvente que ocupa la tarima en la plataforma del Tortón (1.12 × 1.15 × altura).
     */
    public function palletEnvelopeVolumeM3(Pallet $pallet): float
    {
        $height = max(Pallet::WOOD_HEIGHT_M, min(Pallet::MAX_HEIGHT_M, (float) $pallet->estimated_height_m));

        return round(min(2.3825, self::PALLET_FOOTPRINT_AREA_M2 * $height), 3);
    }

    /**
     * Selecciona hasta 12 tarimas sin exceder 12,000 kg ni 28.59 m³, priorizando Ola 1 y FIFO.
     *
     * @param  Collection<int, Pallet>  $slottedPallets
     * @return Collection<int, Pallet>
     */
    private function selectOptimalTortonPallets(Collection $slottedPallets): Collection
    {
        $chosen = collect();
        $accWeight = 0.0;
        $accVolume = 0.0;

        foreach ($slottedPallets as $pallet) {
            if ($chosen->count() >= OutboundOrder::TORTON_MAX_PALLETS) {
                break;
            }

            $w = (float) $pallet->total_weight_kg;
            $v = $this->palletEnvelopeVolumeM3($pallet);

            if (($accWeight + $w) > OutboundOrder::TORTON_MAX_WEIGHT_KG) {
                continue;
            }

            if (($accVolume + $v) > OutboundOrder::TORTON_MAX_VOLUME_M3 + 0.05) {
                continue;
            }

            $chosen->push($pallet);
            $accWeight += $w;
            $accVolume += $v;
        }

        return $chosen;
    }

    /**
     * Ordena las tarimas seleccionadas en las 12 posiciones (6 filas × Izq/Der) del Tortón:
     * tarimas más pesadas al frente/ejes motrices y balance lateral izquierdo/derecho.
     *
     * @param  Collection<int, Pallet>  $selected
     * @return array{sequence: list<array<string, mixed>>, left_weight_kg: float, right_weight_kg: float, imbalance_pct: float}
     */
    private function buildAxleBalancedLoadingSequence(Branch $branch, Collection $selected): array
    {
        $sortedByWeight = $selected->sortByDesc('total_weight_kg')->values();

        $leftSlots = [1, 3, 5, 7, 9, 11];
        $rightSlots = [2, 4, 6, 8, 10, 12];
        $leftIdx = 0;
        $rightIdx = 0;
        $leftWeight = 0.0;
        $rightWeight = 0.0;

        $assigned = [];

        foreach ($sortedByWeight as $pallet) {
            $putLeft = ($leftIdx < count($leftSlots))
                && ($rightIdx >= count($rightSlots) || $leftWeight <= $rightWeight);

            if ($putLeft) {
                $slot = $leftSlots[$leftIdx++];
                $side = 'Izquierda';
                $leftWeight += (float) $pallet->total_weight_kg;
            } else {
                $slot = $rightSlots[$rightIdx++];
                $side = 'Derecha';
                $rightWeight += (float) $pallet->total_weight_kg;
            }

            $rowNum = (int) ceil($slot / 2);
            $axleZone = match (true) {
                $rowNum <= 2 => "Fila {$rowNum} {$side} (Cabina / Eje Delantero)",
                $rowNum <= 4 => "Fila {$rowNum} {$side} (Centro / Eje Motriz)",
                default => "Fila {$rowNum} {$side} (Puerta / Eje Trasero)",
            };

            $assigned[] = [
                'pallet_id' => $pallet->id,
                'pallet_code' => $pallet->code,
                'from_rack' => sprintf('%s · N%d·P%d', $branch->rack_code, (int) $pallet->rack_level, (int) $pallet->rack_position),
                'rack_level' => (int) $pallet->rack_level,
                'rack_position' => (int) $pallet->rack_position,
                'truck_slot' => $slot,
                'truck_slot_label' => sprintf('T-%02d', $slot),
                'axle_zone' => $axleZone,
                'weight_kg' => round((float) $pallet->total_weight_kg, 1),
                'volume_m3' => $this->palletEnvelopeVolumeM3($pallet),
                'box_count' => (int) $pallet->box_count,
                'pallet_profile' => $pallet->pallet_profile,
                'has_liquids' => (bool) $pallet->has_liquids,
                'has_hygroscopic' => (bool) $pallet->has_hygroscopic,
                'skus_summary' => $pallet->items
                    ->map(static fn ($item): string => sprintf('%s (%d)', $item->product?->sku ?? 'SKU', $item->quantity_boxes))
                    ->implode(', '),
            ];
        }

        // La secuencia física de carga al camión va de T-01 (fondo/cabina) hacia T-12 (puertas traseras).
        usort($assigned, static fn (array $a, array $b): int => $a['truck_slot'] <=> $b['truck_slot']);

        $sequence = [];
        foreach ($assigned as $idx => $entry) {
            $entry['step'] = $idx + 1;
            $sequence[] = $entry;
        }

        $total = $leftWeight + $rightWeight;
        $imbalancePct = $total > 0 ? round((abs($leftWeight - $rightWeight) / $total) * 100.0, 1) : 0.0;

        return [
            'sequence' => $sequence,
            'left_weight_kg' => round($leftWeight, 1),
            'right_weight_kg' => round($rightWeight, 1),
            'imbalance_pct' => $imbalancePct,
        ];
    }
}
