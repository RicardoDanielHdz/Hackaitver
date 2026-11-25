<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\OperatorErgonomicLog;
use App\Models\Pallet;
use App\Models\PlannedArrival;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class WarehouseSlottingTopologyGuard
{
    public const int MIN_RACK_NUMBER = 1;

    public const int MAX_RACK_NUMBER = 32;

    public const int MIN_LEVEL = 1;

    public const int MAX_LEVEL = 4;

    public const int MIN_POSITION = 1;

    public const int MAX_POSITION = 8;

    public const int MAX_PALLETS_PER_RACK = 32;

    public const float NOM036_MAX_MANUAL_LOAD_KG = 25.0;

    /**
     * Bitácora en memoria de violaciones bloqueadas durante el ciclo de ejecución.
     *
     * @var list<array<string, mixed>>
     */
    private static array $auditTrail = [];

    /**
     * 1. Aislamiento estricto por Sucursal Destino (RACK-01 a RACK-32).
     */
    public function assertBranchIsolation(
        Branch $targetBranch,
        ?Pallet $targetPallet = null,
        ?PlannedArrival $plannedArrival = null
    ): void {
        $expectedRackCode = sprintf('RACK-%02d', (int) $targetBranch->number);
        if (
            $targetBranch->number < self::MIN_RACK_NUMBER
            || $targetBranch->number > self::MAX_RACK_NUMBER
            || $targetBranch->rack_code !== $expectedRackCode
        ) {
            $this->block(
                'TOPOLOGY_INVALID_BRANCH_RACK',
                sprintf(
                    'GUARD TOPOLÓGICO: La sucursal %s tiene un rack inválido (%s). Solo se permiten RACK-01 a RACK-32.',
                    $targetBranch->code,
                    $targetBranch->rack_code
                ),
                ['branch_code' => $targetBranch->code, 'rack_code' => $targetBranch->rack_code]
            );
        }

        if ($plannedArrival !== null && (int) $plannedArrival->branch_id !== (int) $targetBranch->id) {
            $this->block(
                'BRANCH_ISOLATION_ARRIVAL_MISMATCH',
                sprintf(
                    'AISLAMIENTO DE SUCURSAL VIOLADO: El Aviso de Entrega %s pertenece a otra sucursal (ID %d) y no puede mezclarse en %s (%s).',
                    $plannedArrival->folio,
                    $plannedArrival->branch_id,
                    $targetBranch->code,
                    $targetBranch->rack_code
                ),
                [
                    'arrival_folio' => $plannedArrival->folio,
                    'arrival_branch_id' => $plannedArrival->branch_id,
                    'target_branch_id' => $targetBranch->id,
                ]
            );
        }

        if ($targetPallet !== null && (int) $targetPallet->branch_id !== (int) $targetBranch->id) {
            $this->block(
                'BRANCH_ISOLATION_PALLET_MISMATCH',
                sprintf(
                    'AISLAMIENTO DE SUCURSAL VIOLADO: La tarima %s pertenece a otra sucursal y no puede recibir ni ubicarse en %s (%s).',
                    $targetPallet->code,
                    $targetBranch->code,
                    $targetBranch->rack_code
                ),
                [
                    'pallet_code' => $targetPallet->code,
                    'pallet_branch_id' => $targetPallet->branch_id,
                    'target_branch_id' => $targetBranch->id,
                ]
            );
        }
    }

    /**
     * 2. Validación Topológica de Coordenadas (4 Niveles × 8 Posiciones = 32 por Rack).
     *
     * @param  array<int, array<int, array<string, mixed>>>  $occupiedGrid
     */
    public function assertRackCoordinateTopology(
        Branch $branch,
        Pallet $pallet,
        int $level,
        int $position,
        array $occupiedGrid,
        int $currentSlottedCount
    ): void {
        $this->assertBranchIsolation($branch, $pallet);

        if ($level < self::MIN_LEVEL || $level > self::MAX_LEVEL || $position < self::MIN_POSITION || $position > self::MAX_POSITION) {
            $this->block(
                'TOPOLOGY_COORDINATE_OUT_OF_BOUNDS',
                sprintf(
                    'COORDENADA FUERA DE RANGO (4×8): N%d·P%d es inválida en %s. El rack solo admite Niveles 1..4 y Posiciones 1..8.',
                    $level,
                    $position,
                    $branch->rack_code
                ),
                ['rack' => $branch->rack_code, 'level' => $level, 'position' => $position]
            );
        }

        if ($currentSlottedCount >= self::MAX_PALLETS_PER_RACK) {
            $this->block(
                'TOPOLOGY_RACK_CAPACITY_EXCEEDED',
                sprintf(
                    'CAPACIDAD DE RACK EXCEDIDA: El %s ya tiene las %d posiciones ocupadas.',
                    $branch->rack_code,
                    self::MAX_PALLETS_PER_RACK
                ),
                ['rack' => $branch->rack_code, 'slotted_count' => $currentSlottedCount]
            );
        }

        if (isset($occupiedGrid[$level][$position])) {
            $this->block(
                'TOPOLOGY_COORDINATE_OCCUPIED',
                sprintf(
                    'POSICIÓN OCUPADA: La coordenada %s · N%d·P%d ya contiene una tarima activa y no puede sobrescribirse.',
                    $branch->rack_code,
                    $level,
                    $position
                ),
                ['rack' => $branch->rack_code, 'level' => $level, 'position' => $position]
            );
        }
    }

    /**
     * 3. Límites Físicos Poka-Yoke por Tarima (≤ 1,000 kg con tara 22 kg, ≤ 1.95 m³, ≤ 1.85 m).
     */
    public function assertPalletPhysicalLimits(
        string $palletCode,
        float $projectedTotalWeightKg,
        float $projectedVolumeM3,
        float $projectedHeightM
    ): void {
        if ($projectedTotalWeightKg > Pallet::MAX_TOTAL_WEIGHT_KG + 0.001) {
            $this->block(
                'POKA_YOKE_WEIGHT_EXCEEDED',
                sprintf(
                    'BLOQUEO POKA-YOKE DE SOBREPESO: La tarima %s alcanzaría %.2f kg, excediendo el límite máximo de %.1f kg (incluyendo %.1f kg de tara).',
                    $palletCode,
                    $projectedTotalWeightKg,
                    Pallet::MAX_TOTAL_WEIGHT_KG,
                    Pallet::WOOD_TARE_KG
                ),
                ['pallet_code' => $palletCode, 'projected_weight_kg' => $projectedTotalWeightKg]
            );
        }

        if ($projectedVolumeM3 > Pallet::MAX_USABLE_VOLUME_M3 + 0.0001) {
            $this->block(
                'POKA_YOKE_VOLUME_EXCEEDED',
                sprintf(
                    'BLOQUEO POKA-YOKE DE VOLUMEN: La tarima %s alcanzaría %.4f m³, excediendo el tope útil de %.2f m³.',
                    $palletCode,
                    $projectedVolumeM3,
                    Pallet::MAX_USABLE_VOLUME_M3
                ),
                ['pallet_code' => $palletCode, 'projected_volume_m3' => $projectedVolumeM3]
            );
        }

        if ($projectedHeightM > Pallet::MAX_HEIGHT_M + 0.001) {
            $this->block(
                'POKA_YOKE_HEIGHT_EXCEEDED',
                sprintf(
                    'BLOQUEO POKA-YOKE DE ALTURA: La tarima %s alcanzaría %.2f m, superando el gálibo máximo de %.2f m.',
                    $palletCode,
                    $projectedHeightM,
                    Pallet::MAX_HEIGHT_M
                ),
                ['pallet_code' => $palletCode, 'projected_height_m' => $projectedHeightM]
            );
        }
    }

    /**
     * 4a. Segregación de Materiales Incompatibles en Tarima (Líquidos vs. Papel Higroscópico).
     */
    public function assertPalletMaterialSegregation(
        string $palletCode,
        bool $palletHasLiquids,
        bool $palletHasHygroscopic,
        Product $incomingProduct
    ): void {
        if ($incomingProduct->is_liquid && $palletHasHygroscopic) {
            $this->block(
                'INCOMPATIBLE_LIQUID_ON_HYGROSCOPIC_PALLET',
                sprintf(
                    'SEGREGACIÓN QUÍMICA/HIGROSCÓPICA VIOLADA: No se puede colocar el líquido %s (%s) en la tarima %s que contiene papel higroscópico.',
                    $incomingProduct->sku,
                    $incomingProduct->name,
                    $palletCode
                ),
                ['pallet_code' => $palletCode, 'sku' => $incomingProduct->sku]
            );
        }

        if ($incomingProduct->is_hygroscopic && $palletHasLiquids) {
            $this->block(
                'INCOMPATIBLE_HYGROSCOPIC_ON_LIQUID_PALLET',
                sprintf(
                    'SEGREGACIÓN QUÍMICA/HIGROSCÓPICA VIOLADA: No se puede colocar papel higroscópico %s (%s) en la tarima %s que contiene líquidos.',
                    $incomingProduct->sku,
                    $incomingProduct->name,
                    $palletCode
                ),
                ['pallet_code' => $palletCode, 'sku' => $incomingProduct->sku]
            );
        }
    }

    /**
     * 4b. Segregación Vertical en Columna de Rack 4×8 (Jamás líquidos encima de papel higroscópico).
     *
     * @param  array<int, array<int, array<string, mixed>>>  $occupiedGrid
     */
    public function assertRackColumnSegregation(
        Branch $branch,
        Pallet $pallet,
        int $targetLevel,
        int $targetPosition,
        array $occupiedGrid
    ): void {
        $hasLiquids = (bool) $pallet->has_liquids;
        $hasHygroscopic = (bool) $pallet->has_hygroscopic;

        for ($lvl = self::MIN_LEVEL; $lvl <= self::MAX_LEVEL; $lvl++) {
            if (! isset($occupiedGrid[$lvl][$targetPosition])) {
                continue;
            }

            $existing = $occupiedGrid[$lvl][$targetPosition];

            if ($hasLiquids && $lvl < $targetLevel && ! empty($existing['has_hygroscopic'])) {
                $this->block(
                    'RACK_COLUMN_LIQUID_OVER_HYGROSCOPIC',
                    sprintf(
                        'SEGREGACIÓN VERTICAL VIOLADA EN %s: La tarima líquida %s no puede ubicarse en N%d·P%d por encima de papel higroscópico en N%d·P%d.',
                        $branch->rack_code,
                        $pallet->code,
                        $targetLevel,
                        $targetPosition,
                        $lvl,
                        $targetPosition
                    ),
                    ['rack' => $branch->rack_code, 'pallet' => $pallet->code, 'level' => $targetLevel, 'position' => $targetPosition]
                );
            }

            if ($hasHygroscopic && $lvl > $targetLevel && ! empty($existing['has_liquids'])) {
                $this->block(
                    'RACK_COLUMN_HYGROSCOPIC_UNDER_LIQUID',
                    sprintf(
                        'SEGREGACIÓN VERTICAL VIOLADA EN %s: La tarima higroscópica %s no puede ubicarse en N%d·P%d debajo de líquidos en N%d·P%d.',
                        $branch->rack_code,
                        $pallet->code,
                        $targetLevel,
                        $targetPosition,
                        $lvl,
                        $targetPosition
                    ),
                    ['rack' => $branch->rack_code, 'pallet' => $pallet->code, 'level' => $targetLevel, 'position' => $targetPosition]
                );
            }
        }
    }

    /**
     * 5. Integridad de Estiba Vertical y Resistencia (ECT / Zona Vertical / Límite de Camas).
     */
    public function assertVerticalStackingIntegrity(
        string $palletCode,
        int $palletHighestZonePriority,
        Product $incomingProduct,
        int $projectedSkuBoxesOnPallet
    ): void {
        if ($palletHighestZonePriority > 0 && $palletHighestZonePriority > (int) $incomingProduct->zone_priority) {
            $this->block(
                'VERTICAL_STACKING_CRUSH_HAZARD',
                sprintf(
                    'INTEGRIDAD DE ESTIBA VIOLADA (RIESGO DE APLASTAMIENTO): No se puede estibar %s (Zona %d · %s, %.2f kg) encima de carga de Zona %d más ligera en la tarima %s.',
                    $incomingProduct->sku,
                    $incomingProduct->zone_priority,
                    $incomingProduct->stow_zone,
                    $incomingProduct->master_weight_kg,
                    $palletHighestZonePriority,
                    $palletCode
                ),
                [
                    'pallet_code' => $palletCode,
                    'sku' => $incomingProduct->sku,
                    'incoming_zone' => $incomingProduct->zone_priority,
                    'existing_zone' => $palletHighestZonePriority,
                ]
            );
        }

        $maxBoxesAllowedByLayers = $incomingProduct->boxesPerLayer() * (int) $incomingProduct->max_layers_operational;
        if ($projectedSkuBoxesOnPallet > $maxBoxesAllowedByLayers) {
            $this->block(
                'ECT_LAYER_LIMIT_EXCEEDED',
                sprintf(
                    'LÍMITE DE CAMAS ECT EXCEDIDO: %s admite máximo %d camas (%d cajas por tarima, %d ECT), pero se intentaron colocar %d cajas en %s.',
                    $incomingProduct->sku,
                    $incomingProduct->max_layers_operational,
                    $maxBoxesAllowedByLayers,
                    $incomingProduct->ect_rating,
                    $projectedSkuBoxesOnPallet,
                    $palletCode
                ),
                [
                    'pallet_code' => $palletCode,
                    'sku' => $incomingProduct->sku,
                    'max_boxes_layers' => $maxBoxesAllowedByLayers,
                    'attempted_boxes' => $projectedSkuBoxesOnPallet,
                ]
            );
        }
    }

    /**
     * 6. Cumplimiento Ergonómico y Normativo (NOM-036-1-STPS-2018 y NOM-006-STPS-2023).
     */
    public function assertErgonomicAndMachineryCompliance(
        Product $product,
        int $boxes,
        string $distanceBand,
        ?string $operatorName = null,
        bool $auxiliaryEquipmentConfirmed = true,
        bool $enforceShiftQuotaLimit = false
    ): void {
        if ((float) $product->master_weight_kg > self::NOM036_MAX_MANUAL_LOAD_KG && ! $auxiliaryEquipmentConfirmed) {
            $this->block(
                'NOM036_AUXILIARY_EQUIPMENT_REQUIRED',
                sprintf(
                    'BLOQUEO NOM-036-1-STPS-2018: El SKU %s pesa %.2f kg por caja (> %.1f kg límite máximo manual). Es obligatorio confirmar el uso de equipo auxiliar (patín hidráulico / mesa elevadora).',
                    $product->sku,
                    $product->master_weight_kg,
                    self::NOM036_MAX_MANUAL_LOAD_KG
                ),
                ['sku' => $product->sku, 'weight_kg' => $product->master_weight_kg]
            );
        }

        if ($enforceShiftQuotaLimit && $operatorName !== null && $operatorName !== '') {
            $log = OperatorErgonomicLog::query()
                ->where('operator_name', $operatorName)
                ->where('shift_date', now()->toDateString())
                ->first();

            $currentMovedKg = $log ? (float) $log->moved_weight_kg : 0.0;
            $dailyLimitKg = $distanceBand === '10_to_20m'
                ? OperatorErgonomicLog::LIMIT_10_TO_20M_KG
                : OperatorErgonomicLog::LIMIT_UNDER_10M_KG;
            $projectedKg = round($currentMovedKg + ($boxes * (float) $product->master_weight_kg), 2);

            if ($projectedKg > $dailyLimitKg && ! $auxiliaryEquipmentConfirmed) {
                $this->block(
                    'NOM036_DAILY_SHIFT_QUOTA_EXCEEDED',
                    sprintf(
                        'BLOQUEO NOM-036-1-STPS-2018 (CUOTA DIARIA): El operador %s alcanzaría %.1f kg en el turno (límite %.1f kg para banda %s). Requiere relevo o equipo mecánico.',
                        $operatorName,
                        $projectedKg,
                        $dailyLimitKg,
                        $distanceBand
                    ),
                    ['operator' => $operatorName, 'projected_kg' => $projectedKg, 'limit_kg' => $dailyLimitKg]
                );
            }
        }
    }

    /**
     * Valida cumplimiento NOM-006-STPS-2023 para estiba mecánica en niveles elevados (N2–N4).
     */
    public function assertRackMachineryCompliance(Pallet $pallet, int $targetLevel, bool $machineryConfirmed = true): void
    {
        if ($targetLevel >= 2 && ! $machineryConfirmed) {
            $this->block(
                'NOM006_UPPER_LEVEL_MACHINERY_REQUIRED',
                sprintf(
                    'BLOQUEO NOM-006-STPS-2023: La ubicación de la tarima %s en Nivel %d requiere confirmar uso de maquinaria (apilador eléctrico / montacargas con operador DC-3).',
                    $pallet->code,
                    $targetLevel
                ),
                ['pallet_code' => $pallet->code, 'target_level' => $targetLevel]
            );
        }
    }

    /**
     * Devuelve el historial de violaciones bloqueadas en el proceso actual.
     *
     * @return list<array<string, mixed>>
     */
    public static function recentViolations(): array
    {
        return self::$auditTrail;
    }

    public static function clearViolations(): void
    {
        self::$auditTrail = [];
    }

    /**
     * Registra en bitácora de auditoría y lanza excepción controlada (422 en controlador).
     *
     * @param  array<string, mixed>  $context
     */
    private function block(string $ruleCode, string $message, array $context = []): never
    {
        $entry = [
            'rule_code' => $ruleCode,
            'message' => $message,
            'context' => $context,
            'blocked_at' => now()->toIso8601String(),
        ];

        self::$auditTrail[] = $entry;
        Log::warning('WAREHOUSE_SLOTTING_TOPOLOGY_GUARD_BLOCKED', $entry);

        throw new InvalidArgumentException($message);
    }
}
