<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\OperatorErgonomicLog;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\PlannedArrival;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SlottingEngineService
{
    public const float PALLET_FOOTPRINT_AREA_M2 = 1.288; // 1.12 m × 1.15 m

    public const float PACKING_EFFICIENCY = 0.89; // 1.95 m³ útil / 2.19 m³ bruto

    public function __construct(
        private readonly WarehouseSlottingTopologyGuard $topologyGuard = new WarehouseSlottingTopologyGuard,
    ) {}

    /**
     * Calcula el acomodo recomendado (sin modificar la BD) considerando tarimas abiertas,
     * bloqueos Poka-Yoke, segregación líquido/papel, llegadas posteriores programadas y NOM-036.
     *
     * @return array<string, mixed>
     */
    public function recommendPlacement(
        Branch $branch,
        Product $product,
        int $boxes,
        string $distanceBand = 'under_10m',
        ?string $operatorName = null
    ): array {
        if ($boxes < 1) {
            throw new InvalidArgumentException('La cantidad de bultos debe ser al menos 1.');
        }

        $lookahead = $this->analyzePendingArrivals($branch, $product);
        $openPallets = $this->loadOpenPalletsState($branch);
        $slottedPallets = $branch->pallets()->where('status', 'slotted')->get();

        $allocations = [];
        $remainingBoxes = $boxes;
        $virtualPalletSeq = $this->nextPalletSequenceNumber($branch);
        $simulatedSlotted = $slottedPallets->map(static fn (Pallet $p): array => [
            'level' => (int) $p->rack_level,
            'position' => (int) $p->rack_position,
            'has_liquids' => (bool) $p->has_liquids,
            'has_hygroscopic' => (bool) $p->has_hygroscopic,
            'wave_number' => (int) $p->wave_number,
        ])->values()->all();

        $boxesPerLayer = $product->boxesPerLayer();
        $maxBoxesByLayerCap = $boxesPerLayer * $product->max_layers_operational;

        while ($remainingBoxes > 0) {
            $selectedIndex = $this->selectBestOpenPalletIndex($openPallets, $product, $lookahead);
            $isNewPallet = false;

            if ($selectedIndex === null) {
                $isNewPallet = true;
                $code = sprintf('TAR-%02d-%03d', $branch->number, $virtualPalletSeq++);
                $profile = $this->initialPalletProfile($product, $lookahead);
                $openPallets[] = [
                    'id' => null,
                    'code' => $code,
                    'is_new' => true,
                    'goods_weight_kg' => 0.0,
                    'total_weight_kg' => Pallet::WOOD_TARE_KG,
                    'volume_m3' => 0.0,
                    'estimated_height_m' => Pallet::WOOD_HEIGHT_M,
                    'box_count' => 0,
                    'highest_zone_priority' => 0,
                    'has_liquids' => false,
                    'has_hygroscopic' => false,
                    'pallet_profile' => $profile,
                    'sku_boxes' => [],
                ];
                $selectedIndex = count($openPallets) - 1;
            }

            $palletState = &$openPallets[$selectedIndex];
            $capacity = $this->computeAvailableBoxCapacity($palletState, $product, $lookahead);

            if ($capacity['max_boxes'] <= 0) {
                if (! $isNewPallet) {
                    $palletState['full_locked'] = true;
                    unset($palletState);

                    continue;
                }

                // Salvaguarda: al menos 1 caja si cabe en tarima vacía.
                $capacity['max_boxes'] = 1;
            }

            $placedNow = min($remainingBoxes, $capacity['max_boxes']);
            $existingSkuBoxes = (int) ($palletState['sku_boxes'][$product->id] ?? 0);
            $newSkuBoxes = $existingSkuBoxes + $placedNow;
            $layerIndex = (int) ceil($newSkuBoxes / $boxesPerLayer);

            $addedWeight = round($placedNow * $product->master_weight_kg, 2);
            $addedVolume = round($placedNow * $product->master_volume_m3, 4);

            $palletState['goods_weight_kg'] = round($palletState['goods_weight_kg'] + $addedWeight, 2);
            $palletState['total_weight_kg'] = round(Pallet::WOOD_TARE_KG + $palletState['goods_weight_kg'], 2);
            $palletState['volume_m3'] = round($palletState['volume_m3'] + $addedVolume, 4);
            $palletState['estimated_height_m'] = $this->estimatePalletHeightM($palletState['volume_m3']);
            $palletState['box_count'] += $placedNow;
            $palletState['highest_zone_priority'] = max($palletState['highest_zone_priority'], $product->zone_priority);
            $palletState['has_liquids'] = $palletState['has_liquids'] || $product->is_liquid;
            $palletState['has_hygroscopic'] = $palletState['has_hygroscopic'] || $product->is_hygroscopic;
            $palletState['pallet_profile'] = $this->resolvePalletProfile($palletState);
            $palletState['sku_boxes'][$product->id] = $newSkuBoxes;

            $shouldAutoSlot = $this->shouldAutoSlotState($palletState, $product, $newSkuBoxes, $maxBoxesByLayerCap, $lookahead);
            $rackPreview = $this->findOptimalRackSlotFromArray(
                $simulatedSlotted,
                $palletState['total_weight_kg'],
                $palletState['has_liquids'],
                $palletState['has_hygroscopic'],
                $palletState['pallet_profile']
            );

            if ($shouldAutoSlot && $rackPreview['available']) {
                $simulatedSlotted[] = [
                    'level' => $rackPreview['level'],
                    'position' => $rackPreview['position'],
                    'has_liquids' => $palletState['has_liquids'],
                    'has_hygroscopic' => $palletState['has_hygroscopic'],
                    'wave_number' => $rackPreview['wave_number'],
                ];
                $palletState['full_locked'] = true;
            }

            $allocations[] = [
                'pallet_id' => $palletState['id'],
                'pallet_code' => $palletState['code'],
                'is_new_pallet' => $isNewPallet,
                'boxes_placed' => $placedNow,
                'assigned_zone' => $product->stow_zone,
                'assigned_zone_priority' => $product->zone_priority,
                'layer_index' => $layerIndex,
                'max_layers_operational' => $product->max_layers_operational,
                'boxes_per_layer' => $boxesPerLayer,
                'added_weight_kg' => $addedWeight,
                'added_volume_m3' => $addedVolume,
                'projected_total_weight_kg' => $palletState['total_weight_kg'],
                'projected_volume_m3' => $palletState['volume_m3'],
                'projected_height_m' => $palletState['estimated_height_m'],
                'weight_fill_pct' => round(($palletState['total_weight_kg'] / Pallet::MAX_TOTAL_WEIGHT_KG) * 100, 1),
                'volume_fill_pct' => round(($palletState['volume_m3'] / Pallet::MAX_USABLE_VOLUME_M3) * 100, 1),
                'pallet_profile' => $palletState['pallet_profile'],
                'should_auto_slot' => $shouldAutoSlot,
                'limiting_factor' => $capacity['limiting_factor'],
                'projected_rack_slot' => $rackPreview['available']
                    ? sprintf('%s · N%d·P%d', $branch->rack_code, $rackPreview['level'], $rackPreview['position'])
                    : 'RACK LLENO (32/32)',
                'projected_rack_level' => $rackPreview['level'],
                'projected_rack_position' => $rackPreview['position'],
                'projected_wave' => $rackPreview['wave_number'],
                'rack_reason' => $rackPreview['reason'],
                'placement_reason' => $this->buildPlacementReason(
                    $product,
                    $isNewPallet,
                    $capacity['limiting_factor'],
                    $capacity['reserved_for_lookahead'],
                    $lookahead,
                    $palletState
                ),
            ];

            $remainingBoxes -= $placedNow;
            unset($palletState);
        }

        $totalIncomingWeightKg = round($boxes * $product->master_weight_kg, 2);
        $totalIncomingVolumeM3 = round($boxes * $product->master_volume_m3, 4);
        $ergonomics = $this->evaluateErgonomics($product, $boxes, $distanceBand, $operatorName);

        return [
            'branch' => [
                'id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
                'rack_code' => $branch->rack_code,
            ],
            'product' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'category' => $product->category,
                'stow_zone' => $product->stow_zone,
                'zone_priority' => $product->zone_priority,
                'master_weight_kg' => $product->master_weight_kg,
                'master_volume_m3' => $product->master_volume_m3,
                'master_density_kg_m3' => $product->master_density_kg_m3,
                'ect_rating' => $product->ect_rating,
                'max_layers_ect' => $product->max_layers_ect,
                'max_layers_operational' => $product->max_layers_operational,
                'boxes_per_layer' => $boxesPerLayer,
                'orientation_rule' => $product->orientation_rule,
                'is_liquid' => $product->is_liquid,
                'is_hygroscopic' => $product->is_hygroscopic,
                'is_temp_sensitive' => $product->is_temp_sensitive,
                'is_crushable' => $product->is_crushable,
                'is_high_density_metal' => $product->is_high_density_metal,
                'handling_notes' => $product->handling_notes,
            ],
            'boxes_requested' => $boxes,
            'total_incoming_weight_kg' => $totalIncomingWeightKg,
            'total_incoming_volume_m3' => $totalIncomingVolumeM3,
            'lookahead' => $lookahead,
            'ergonomics' => $ergonomics,
            'allocations' => $allocations,
        ];
    }

    /**
     * Ejecuta físicamente la recepción y acomodo de bultos en BD, cerrando/ubicando tarimas llenas
     * y registrando la bitácora ergonómica NOM-036 del operador.
     *
     * @return array<string, mixed>
     */
    public function receiveBoxes(
        Branch $branch,
        Product $product,
        int $boxes,
        string $distanceBand = 'under_10m',
        ?int $userId = null,
        ?string $operatorName = null,
        ?PlannedArrival $plannedArrival = null,
        bool $autoSlotWhenFull = true,
        ?string $scannedBultoCode = null,
        ?string $originSupplier = null,
        ?string $originDock = null,
        ?Pallet $forcedTargetPallet = null,
        bool $auxiliaryEquipmentConfirmed = true
    ): array {
        if ($boxes < 1) {
            throw new InvalidArgumentException('La cantidad de bultos debe ser al menos 1.');
        }

        // Validaciones previas del Guard Topológico y Normativo (Aislamiento, NOM-036 y Tarima forzada)
        $this->topologyGuard->assertBranchIsolation($branch, $forcedTargetPallet, $plannedArrival);
        $this->topologyGuard->assertErgonomicAndMachineryCompliance(
            $product,
            $boxes,
            $distanceBand,
            $operatorName,
            $auxiliaryEquipmentConfirmed,
            ! $auxiliaryEquipmentConfirmed
        );

        if ($forcedTargetPallet !== null) {
            $this->validateForcedPalletPlacement($forcedTargetPallet, $product, $boxes);
        }

        return DB::transaction(function () use (
            $branch,
            $product,
            $boxes,
            $distanceBand,
            $userId,
            $operatorName,
            $plannedArrival,
            $autoSlotWhenFull,
            $scannedBultoCode,
            $originSupplier,
            $originDock,
            $forcedTargetPallet
        ): array {
            $lookahead = $this->analyzePendingArrivals($branch, $product, $plannedArrival?->id);
            $boxesPerLayer = $product->boxesPerLayer();
            $maxBoxesByLayerCap = $boxesPerLayer * $product->max_layers_operational;

            $remainingBoxes = $boxes;
            $executedAllocations = [];
            $batchPrefix = $plannedArrival?->folio ?? ('REC-'.now()->format('His'));

            while ($remainingBoxes > 0) {
                $openPallets = $this->loadOpenPalletsState($branch);
                if ($forcedTargetPallet !== null) {
                    $selectedIndex = collect($openPallets)->search(
                        static fn (array $p): bool => (int) $p['id'] === (int) $forcedTargetPallet->id
                    );
                    $selectedIndex = $selectedIndex === false ? null : (int) $selectedIndex;
                } else {
                    $selectedIndex = $this->selectBestOpenPalletIndex($openPallets, $product, $lookahead);
                }
                $isNewPallet = false;

                if ($selectedIndex === null) {
                    $isNewPallet = true;
                    $pallet = Pallet::create([
                        'code' => $this->generateNextPalletCode($branch),
                        'branch_id' => $branch->id,
                        'status' => 'building',
                        'tare_weight_kg' => Pallet::WOOD_TARE_KG,
                        'goods_weight_kg' => 0.0,
                        'total_weight_kg' => Pallet::WOOD_TARE_KG,
                        'volume_m3' => 0.0,
                        'estimated_height_m' => Pallet::WOOD_HEIGHT_M,
                        'box_count' => 0,
                        'highest_zone_priority' => 0,
                        'has_liquids' => false,
                        'has_hygroscopic' => false,
                        'pallet_profile' => $this->initialPalletProfile($product, $lookahead),
                        'wave_number' => $this->determineNextWaveNumber($branch),
                    ]);

                    $palletState = [
                        'id' => $pallet->id,
                        'code' => $pallet->code,
                        'is_new' => true,
                        'goods_weight_kg' => 0.0,
                        'total_weight_kg' => Pallet::WOOD_TARE_KG,
                        'volume_m3' => 0.0,
                        'estimated_height_m' => Pallet::WOOD_HEIGHT_M,
                        'box_count' => 0,
                        'highest_zone_priority' => 0,
                        'has_liquids' => false,
                        'has_hygroscopic' => false,
                        'pallet_profile' => $pallet->pallet_profile,
                        'sku_boxes' => [],
                    ];
                } else {
                    $palletState = $openPallets[$selectedIndex];
                    /** @var Pallet $pallet */
                    $pallet = Pallet::query()->findOrFail($palletState['id']);
                }

                $capacity = $this->computeAvailableBoxCapacity($palletState, $product, $lookahead);
                if ($capacity['max_boxes'] <= 0) {
                    if (! $isNewPallet) {
                        // Cerrar esta tarima llena en el rack y continuar con otra.
                        $this->slotPalletIntoRack($pallet);

                        continue;
                    }
                    $capacity['max_boxes'] = 1;
                }

                $placedNow = min($remainingBoxes, $capacity['max_boxes']);
                $existingSkuBoxes = (int) ($palletState['sku_boxes'][$product->id] ?? 0);
                $newSkuBoxes = $existingSkuBoxes + $placedNow;
                $layerIndex = (int) ceil($newSkuBoxes / $boxesPerLayer);

                $addedWeight = round($placedNow * $product->master_weight_kg, 2);
                $addedVolume = round($placedNow * $product->master_volume_m3, 4);

                $palletState['goods_weight_kg'] = round($palletState['goods_weight_kg'] + $addedWeight, 2);
                $palletState['total_weight_kg'] = round(Pallet::WOOD_TARE_KG + $palletState['goods_weight_kg'], 2);
                $palletState['volume_m3'] = round($palletState['volume_m3'] + $addedVolume, 4);
                $palletState['estimated_height_m'] = $this->estimatePalletHeightM($palletState['volume_m3']);
                $palletState['box_count'] += $placedNow;
                $palletState['highest_zone_priority'] = max($palletState['highest_zone_priority'], $product->zone_priority);
                $palletState['has_liquids'] = $palletState['has_liquids'] || $product->is_liquid;
                $palletState['has_hygroscopic'] = $palletState['has_hygroscopic'] || $product->is_hygroscopic;
                $palletState['pallet_profile'] = $this->resolvePalletProfile($palletState);

                $reason = $this->buildPlacementReason(
                    $product,
                    $isNewPallet,
                    $capacity['limiting_factor'],
                    $capacity['reserved_for_lookahead'],
                    $lookahead,
                    $palletState
                );

                // Verificación final del Guard Topológico antes de persistir cambios en la tarima
                $this->topologyGuard->assertPalletMaterialSegregation(
                    (string) $palletState['code'],
                    (bool) $pallet->has_liquids,
                    (bool) $pallet->has_hygroscopic,
                    $product
                );
                $this->topologyGuard->assertVerticalStackingIntegrity(
                    (string) $palletState['code'],
                    (int) $pallet->highest_zone_priority,
                    $product,
                    $newSkuBoxes
                );
                $this->topologyGuard->assertPalletPhysicalLimits(
                    (string) $palletState['code'],
                    (float) $palletState['total_weight_kg'],
                    (float) $palletState['volume_m3'],
                    (float) $palletState['estimated_height_m']
                );

                $pallet->update([
                    'goods_weight_kg' => $palletState['goods_weight_kg'],
                    'total_weight_kg' => $palletState['total_weight_kg'],
                    'volume_m3' => $palletState['volume_m3'],
                    'estimated_height_m' => $palletState['estimated_height_m'],
                    'box_count' => $palletState['box_count'],
                    'highest_zone_priority' => $palletState['highest_zone_priority'],
                    'has_liquids' => $palletState['has_liquids'],
                    'has_hygroscopic' => $palletState['has_hygroscopic'],
                    'pallet_profile' => $palletState['pallet_profile'],
                ]);

                $slottedResult = null;
                if ($autoSlotWhenFull && $this->shouldAutoSlotState($palletState, $product, $newSkuBoxes, $maxBoxesByLayerCap, $lookahead)) {
                    $slottedResult = $this->slotPalletIntoRack($pallet->fresh());
                }

                // Coordenada exacta (real si ya subió a rack, o proyectada para la etiqueta láser interna)
                if ($slottedResult !== null) {
                    $plannedCoordinate = $slottedResult['coordinate'];
                    $waveNum = (int) $slottedResult['wave_number'];
                } else {
                    $currentSlotted = $branch->pallets()
                        ->where('status', 'slotted')
                        ->get()
                        ->map(static fn (Pallet $p): array => [
                            'level' => (int) $p->rack_level,
                            'position' => (int) $p->rack_position,
                            'has_liquids' => (bool) $p->has_liquids,
                            'has_hygroscopic' => (bool) $p->has_hygroscopic,
                            'wave_number' => (int) $p->wave_number,
                        ])->values()->all();
                    $preview = $this->findOptimalRackSlotFromArray(
                        $currentSlotted,
                        $palletState['total_weight_kg'],
                        $palletState['has_liquids'],
                        $palletState['has_hygroscopic'],
                        $palletState['pallet_profile']
                    );
                    $plannedCoordinate = $preview['available']
                        ? sprintf('%s · N%d·P%d', $branch->rack_code, $preview['level'], $preview['position'])
                        : ($branch->rack_code.' · EN PISO');
                    $waveNum = (int) $preview['wave_number'];
                }

                $outboundDock = $branch->number <= 11 ? 'E1' : ($branch->number <= 22 ? 'E2' : 'E3');
                $outboundTarget = sprintf('ANDÉN %s · OLA %d TORTÓN', $outboundDock, $waveNum);
                $outboundCutoff = $waveNum === 1 ? 'SALIDA PRÓXIMO CORTE (≥90%)' : 'SEGUNDO CORTE PROGRAMADO';
                $resolvedSupplier = $originSupplier ?: ($plannedArrival?->supplier_name ?? 'Proveedor Autorizado CEDIS');
                $resolvedDock = $originDock ?: ($plannedArrival?->dock_code ?? 'R2');
                $internalLabelCode = sprintf('LSR-%02d-%04d', $branch->number, ((int) PalletItem::query()->max('id')) + 1);

                $createdItem = PalletItem::create([
                    'pallet_id' => $pallet->id,
                    'product_id' => $product->id,
                    'batch_code' => $batchPrefix,
                    'internal_label_code' => $internalLabelCode,
                    'scanned_bulto_code' => $scannedBultoCode,
                    'quantity_boxes' => $placedNow,
                    'assigned_zone_priority' => $product->zone_priority,
                    'layer_index' => $layerIndex,
                    'total_weight_kg' => $addedWeight,
                    'total_volume_m3' => $addedVolume,
                    'orientation_instruction' => $product->orientation_rule,
                    'placement_reason' => $reason,
                    'ergonomic_warning' => $product->ergonomicAlertMessage(),
                    'origin_supplier' => $resolvedSupplier,
                    'origin_dock' => $resolvedDock,
                    'planned_rack_coordinate' => $plannedCoordinate,
                    'outbound_dock_target' => $outboundTarget,
                    'outbound_cutoff' => $outboundCutoff,
                    'placed_by_user_id' => $userId,
                ]);

                $executedAllocations[] = [
                    'pallet_item_id' => $createdItem->id,
                    'internal_label_code' => $internalLabelCode,
                    'pallet_id' => $pallet->id,
                    'pallet_code' => $pallet->code,
                    'is_new_pallet' => $isNewPallet,
                    'boxes_placed' => $placedNow,
                    'assigned_zone' => $product->stow_zone,
                    'zone_priority' => $product->zone_priority,
                    'layer_index' => $layerIndex,
                    'added_weight_kg' => $addedWeight,
                    'added_volume_m3' => $addedVolume,
                    'pallet_total_weight_kg' => $palletState['total_weight_kg'],
                    'pallet_volume_m3' => $palletState['volume_m3'],
                    'pallet_height_m' => $palletState['estimated_height_m'],
                    'placement_reason' => $reason,
                    'planned_rack_coordinate' => $plannedCoordinate,
                    'origin_supplier' => $resolvedSupplier,
                    'origin_dock' => $resolvedDock,
                    'origin_folio' => $batchPrefix,
                    'outbound_dock_target' => $outboundTarget,
                    'outbound_cutoff' => $outboundCutoff,
                    'auto_slotted' => $slottedResult !== null,
                    'rack_assignment' => $slottedResult,
                ];

                $remainingBoxes -= $placedNow;
            }

            if ($plannedArrival !== null) {
                $newReceived = min($plannedArrival->boxes_expected, $plannedArrival->boxes_received + $boxes);
                $plannedArrival->update([
                    'boxes_received' => $newReceived,
                    'status' => $newReceived >= $plannedArrival->boxes_expected ? 'received' : 'partial',
                ]);
            }

            $ergonomicsLog = $this->recordErgonomicActivity(
                $product,
                $boxes,
                $distanceBand,
                $userId,
                $operatorName ?? 'Operador Turno CEDIS'
            );

            return [
                'product_sku' => $product->sku,
                'product_name' => $product->name,
                'boxes_processed' => $boxes,
                'allocations' => $executedAllocations,
                'ergonomic_alert' => $product->ergonomicAlertMessage(),
                'operator_shift_log' => [
                    'operator_name' => $ergonomicsLog->operator_name,
                    'distance_band' => $ergonomicsLog->distance_band,
                    'moved_weight_kg' => $ergonomicsLog->moved_weight_kg,
                    'daily_limit_kg' => $ergonomicsLog->dailyLimitKg(),
                    'usage_pct' => $ergonomicsLog->usagePct(),
                    'assisted_moves' => $ergonomicsLog->assisted_moves,
                ],
            ];
        });
    }

    /**
     * Procesa TODAS las llegadas programadas pendientes de una sucursal ejecutando la clave canónica WMS:
     * ORDER BY zone_priority ASC, master_density_kg_m3 DESC, master_weight_kg DESC.
     *
     * @return array<string, mixed>
     */
    public function receiveScheduledBatch(
        Branch $branch,
        string $distanceBand = 'under_10m',
        ?int $userId = null,
        ?string $operatorName = null
    ): array {
        $arrivals = PlannedArrival::query()
            ->with('product')
            ->join('products', 'planned_arrivals.product_id', '=', 'products.id')
            ->where('planned_arrivals.branch_id', $branch->id)
            ->whereIn('planned_arrivals.status', ['scheduled', 'partial'])
            ->orderBy('products.zone_priority', 'asc')
            ->orderBy('products.master_density_kg_m3', 'desc')
            ->orderBy('products.master_weight_kg', 'desc')
            ->select('planned_arrivals.*')
            ->get();

        if ($arrivals->isEmpty()) {
            return [
                'branch_code' => $branch->code,
                'steps' => [],
                'total_boxes' => 0,
                'total_weight_kg' => 0.0,
                'message' => 'No hay llegadas programadas pendientes para esta sucursal.',
            ];
        }

        $steps = [];
        $totalBoxes = 0;
        $totalWeightKg = 0.0;

        foreach ($arrivals as $idx => $arrival) {
            $pending = $arrival->pendingBoxes();
            if ($pending <= 0) {
                continue;
            }

            /** @var Product $product */
            $product = $arrival->product;
            $result = $this->receiveBoxes(
                $branch,
                $product,
                $pending,
                $distanceBand,
                $userId,
                $operatorName,
                $arrival,
                true
            );

            $totalBoxes += $pending;
            $totalWeightKg = round($totalWeightKg + ($pending * $product->master_weight_kg), 2);

            $steps[] = [
                'step' => $idx + 1,
                'folio' => $arrival->folio,
                'sku' => $product->sku,
                'product_name' => $product->name,
                'stow_zone' => $product->stow_zone,
                'zone_priority' => $product->zone_priority,
                'master_density_kg_m3' => $product->master_density_kg_m3,
                'master_weight_kg' => $product->master_weight_kg,
                'boxes' => $pending,
                'orientation_rule' => $product->orientation_rule,
                'ergonomic_alert' => $result['ergonomic_alert'],
                'allocations' => $result['allocations'],
            ];
        }

        return [
            'branch_code' => $branch->code,
            'sort_formula' => 'ORDER BY prioridad_zona ASC, densidad_master DESC, peso_bruto_master DESC',
            'steps' => $steps,
            'total_boxes' => $totalBoxes,
            'total_weight_kg' => $totalWeightKg,
        ];
    }

    /**
     * Cierra una tarima en armado y la ubica en la posición óptima (o explícita validada) del Rack 4×8 de la sucursal.
     *
     * @return array<string, mixed>
     */
    public function slotPalletIntoRack(
        Pallet $pallet,
        ?int $explicitLevel = null,
        ?int $explicitPosition = null,
        ?Branch $explicitBranch = null,
        bool $machineryConfirmed = true
    ): array {
        $branch = $explicitBranch ?? $pallet->branch;
        $this->topologyGuard->assertBranchIsolation($branch, $pallet);
        $this->topologyGuard->assertPalletPhysicalLimits(
            $pallet->code,
            (float) $pallet->total_weight_kg,
            (float) $pallet->volume_m3,
            (float) $pallet->estimated_height_m
        );

        if (
            $explicitLevel === null
            && $explicitPosition === null
            && $pallet->status === 'slotted'
            && $pallet->rack_level !== null
            && $pallet->rack_position !== null
        ) {
            return [
                'available' => true,
                'pallet_code' => $pallet->code,
                'level' => $pallet->rack_level,
                'position' => $pallet->rack_position,
                'coordinate' => $pallet->rackCoordinate(),
                'wave_number' => $pallet->wave_number,
                'reason' => 'Tarima ya ubicada previamente en el rack.',
            ];
        }

        $slotted = $branch->pallets()
            ->where('status', 'slotted')
            ->where('id', '!=', $pallet->id)
            ->get()
            ->map(static fn (Pallet $p): array => [
                'level' => (int) $p->rack_level,
                'position' => (int) $p->rack_position,
                'has_liquids' => (bool) $p->has_liquids,
                'has_hygroscopic' => (bool) $p->has_hygroscopic,
                'wave_number' => (int) $p->wave_number,
            ])
            ->values()
            ->all();

        $occupiedGrid = [];
        $wave1Count = 0;
        foreach ($slotted as $sp) {
            $occupiedGrid[$sp['level']][$sp['position']] = $sp;
            if ($sp['wave_number'] === 1) {
                $wave1Count++;
            }
        }

        if ($explicitLevel !== null || $explicitPosition !== null) {
            $targetLevel = (int) ($explicitLevel ?? 1);
            $targetPos = (int) ($explicitPosition ?? 1);

            $this->topologyGuard->assertRackCoordinateTopology(
                $branch,
                $pallet,
                $targetLevel,
                $targetPos,
                $occupiedGrid,
                count($slotted)
            );
            $this->topologyGuard->assertRackColumnSegregation(
                $branch,
                $pallet,
                $targetLevel,
                $targetPos,
                $occupiedGrid
            );
            $this->topologyGuard->assertRackMachineryCompliance($pallet, $targetLevel, $machineryConfirmed);

            $slot = [
                'available' => true,
                'level' => $targetLevel,
                'position' => $targetPos,
                'wave_number' => $wave1Count < 12 ? 1 : 2,
                'reason' => sprintf('Ubicación validada por WarehouseSlottingTopologyGuard en %s · N%d·P%d.', $branch->rack_code, $targetLevel, $targetPos),
            ];
        } else {
            $slot = $this->findOptimalRackSlotFromArray(
                $slotted,
                (float) $pallet->total_weight_kg,
                (bool) $pallet->has_liquids,
                (bool) $pallet->has_hygroscopic,
                $pallet->pallet_profile
            );

            if (! $slot['available']) {
                throw new InvalidArgumentException("El {$branch->rack_code} ya tiene las 32 posiciones ocupadas; despacha una Orden de Salida para liberar espacio.");
            }

            $this->topologyGuard->assertRackCoordinateTopology(
                $branch,
                $pallet,
                (int) $slot['level'],
                (int) $slot['position'],
                $occupiedGrid,
                count($slotted)
            );
            $this->topologyGuard->assertRackColumnSegregation(
                $branch,
                $pallet,
                (int) $slot['level'],
                (int) $slot['position'],
                $occupiedGrid
            );
            $this->topologyGuard->assertRackMachineryCompliance($pallet, (int) $slot['level'], $machineryConfirmed);
        }

        $pallet->update([
            'status' => 'slotted',
            'rack_level' => $slot['level'],
            'rack_position' => $slot['position'],
            'wave_number' => $slot['wave_number'],
            'slotted_at' => now(),
        ]);

        return [
            'available' => true,
            'pallet_code' => $pallet->code,
            'rack_code' => $branch->rack_code,
            'level' => $slot['level'],
            'position' => $slot['position'],
            'coordinate' => sprintf('%s · N%d·P%d', $branch->rack_code, $slot['level'], $slot['position']),
            'wave_number' => $slot['wave_number'],
            'reason' => $slot['reason'],
        ];
    }

    /**
     * Valida una asignación forzada sobre una tarima específica antes de procesarla.
     */
    private function validateForcedPalletPlacement(Pallet $pallet, Product $product, int $boxes): void
    {
        $pallet->loadMissing('items');

        $existingSkuBoxes = (int) $pallet->items
            ->where('product_id', $product->id)
            ->sum('quantity_boxes');
        $projectedSkuBoxes = $existingSkuBoxes + $boxes;

        $projectedGoodsWeight = round((float) $pallet->goods_weight_kg + ($boxes * (float) $product->master_weight_kg), 2);
        $projectedTotalWeight = round(Pallet::WOOD_TARE_KG + $projectedGoodsWeight, 2);
        $projectedVolume = round((float) $pallet->volume_m3 + ($boxes * (float) $product->master_volume_m3), 4);
        $projectedHeight = round(
            Pallet::WOOD_HEIGHT_M + (($projectedVolume / self::PACKING_EFFICIENCY) / self::PALLET_FOOTPRINT_AREA_M2),
            2
        );

        $this->topologyGuard->assertPalletMaterialSegregation(
            $pallet->code,
            (bool) $pallet->has_liquids,
            (bool) $pallet->has_hygroscopic,
            $product
        );
        $this->topologyGuard->assertVerticalStackingIntegrity(
            $pallet->code,
            (int) $pallet->highest_zone_priority,
            $product,
            $projectedSkuBoxes
        );
        $this->topologyGuard->assertPalletPhysicalLimits(
            $pallet->code,
            $projectedTotalWeight,
            $projectedVolume,
            $projectedHeight
        );
    }

    /**
     * Evalúa el volumen envolvente real de la tarima dentro de la caja del Tortón (1.12 × 1.15 × altura).
     */
    public function estimatePalletHeightM(float $usableBoxVolumeM3): float
    {
        if ($usableBoxVolumeM3 <= 0.0) {
            return Pallet::WOOD_HEIGHT_M;
        }

        $grossLoadVolumeM3 = $usableBoxVolumeM3 / self::PACKING_EFFICIENCY;
        $loadHeightM = $grossLoadVolumeM3 / self::PALLET_FOOTPRINT_AREA_M2;

        return round(min(Pallet::MAX_HEIGHT_M, Pallet::WOOD_HEIGHT_M + $loadHeightM), 2);
    }

    /**
     * Analiza las llegadas futuras programadas para anticipar el acomodo posterior.
     *
     * @return array<string, mixed>
     */
    public function analyzePendingArrivals(Branch $branch, ?Product $incomingProduct = null, ?int $excludeArrivalId = null): array
    {
        $query = PlannedArrival::query()
            ->with('product')
            ->where('branch_id', $branch->id)
            ->whereIn('status', ['scheduled', 'partial']);

        if ($excludeArrivalId !== null) {
            $query->where('id', '!=', $excludeArrivalId);
        }

        $arrivals = $query->get();

        $pendingBaseBoxes = 0;
        $pendingInterBoxes = 0;
        $pendingLightBoxes = 0;
        $pendingLightVolumeM3 = 0.0;
        $pendingBaseWeightKg = 0.0;
        $pendingLiquids = 0;
        $pendingHygroscopic = 0;

        foreach ($arrivals as $arrival) {
            $qty = $arrival->pendingBoxes();
            if ($qty <= 0 || $arrival->product === null) {
                continue;
            }

            $prod = $arrival->product;
            if ($prod->zone_priority === 1) {
                $pendingBaseBoxes += $qty;
                $pendingBaseWeightKg += $qty * $prod->master_weight_kg;
            } elseif ($prod->zone_priority === 2) {
                $pendingInterBoxes += $qty;
                $pendingLightVolumeM3 += $qty * $prod->master_volume_m3;
            } else {
                $pendingLightBoxes += $qty;
                $pendingLightVolumeM3 += $qty * $prod->master_volume_m3;
            }

            if ($prod->is_liquid) {
                $pendingLiquids += $qty;
            }
            if ($prod->is_hygroscopic) {
                $pendingHygroscopic += $qty;
            }
        }

        $advisories = [];
        if ($incomingProduct !== null) {
            if ($incomingProduct->zone_priority === 1 && ! $incomingProduct->is_liquid && ($pendingInterBoxes + $pendingLightBoxes) > 0) {
                $advisories[] = sprintf(
                    'Acomodo posterior detectado: vienen %d bultos Intermedio/Ligero (%.2f m³). Se reserva cabecera superior (~0.55 m³) en la tarima para estiba mixta estratificada.',
                    $pendingInterBoxes + $pendingLightBoxes,
                    $pendingLightVolumeM3
                );
            }

            if ($incomingProduct->zone_priority === 3 && $pendingBaseBoxes > 0) {
                $advisories[] = sprintf(
                    'Llegada anticipada de carga Superior/Ligero mientras hay %d bultos Base/Pesado (%.0f kg) por arribar. Se prioriza montar sobre tarima con base sólida o segregar en tarima ligera (Nivel 3–4) para no bloquear Nivel 1.',
                    $pendingBaseBoxes,
                    $pendingBaseWeightKg
                );
            }

            if ($incomingProduct->is_liquid && $pendingHygroscopic > 0) {
                $advisories[] = sprintf(
                    'Segregación preventiva: hay %d bultos de papel higroscópico programados. Los líquidos se confinan en tarima segregada (Nivel 1) sin papel debajo.',
                    $pendingHygroscopic
                );
            }

            if ($incomingProduct->is_hygroscopic && $pendingLiquids > 0) {
                $advisories[] = sprintf(
                    'Protección higroscópica: hay %d bultos líquidos programados. Esta tarima queda blindada contra estiba de líquidos en su columna vertical del rack.',
                    $pendingLiquids
                );
            }
        }

        return [
            'pending_lines' => $arrivals->count(),
            'pending_base_boxes' => $pendingBaseBoxes,
            'pending_inter_boxes' => $pendingInterBoxes,
            'pending_light_boxes' => $pendingLightBoxes,
            'pending_light_volume_m3' => round($pendingLightVolumeM3, 3),
            'pending_base_weight_kg' => round($pendingBaseWeightKg, 1),
            'pending_liquid_boxes' => $pendingLiquids,
            'pending_hygroscopic_boxes' => $pendingHygroscopic,
            'advisories' => $advisories,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadOpenPalletsState(Branch $branch): array
    {
        /** @var Collection<int, Pallet> $pallets */
        $pallets = $branch->pallets()
            ->with('items')
            ->where('status', 'building')
            ->orderBy('id')
            ->get();

        return $pallets->map(static function (Pallet $pallet): array {
            $skuBoxes = [];
            foreach ($pallet->items as $item) {
                $skuBoxes[$item->product_id] = ($skuBoxes[$item->product_id] ?? 0) + (int) $item->quantity_boxes;
            }

            return [
                'id' => $pallet->id,
                'code' => $pallet->code,
                'is_new' => false,
                'goods_weight_kg' => (float) $pallet->goods_weight_kg,
                'total_weight_kg' => (float) $pallet->total_weight_kg,
                'volume_m3' => (float) $pallet->volume_m3,
                'estimated_height_m' => (float) $pallet->estimated_height_m,
                'box_count' => (int) $pallet->box_count,
                'highest_zone_priority' => (int) $pallet->highest_zone_priority,
                'has_liquids' => (bool) $pallet->has_liquids,
                'has_hygroscopic' => (bool) $pallet->has_hygroscopic,
                'pallet_profile' => $pallet->pallet_profile,
                'sku_boxes' => $skuBoxes,
            ];
        })->values()->all();
    }

    /**
     * Selecciona la mejor tarima abierta compatible aplicando todas las reglas Poka-Yoke.
     *
     * @param  list<array<string, mixed>>  $openPallets
     * @param  array<string, mixed>  $lookahead
     */
    private function selectBestOpenPalletIndex(array $openPallets, Product $product, array $lookahead): ?int
    {
        $bestIndex = null;
        $bestScore = -99999;

        foreach ($openPallets as $idx => $pallet) {
            if (! empty($pallet['full_locked'])) {
                continue;
            }

            // Regla 1: No aplastamiento — jamás poner zona más pesada encima de una más ligera.
            if ($pallet['highest_zone_priority'] > $product->zone_priority) {
                continue;
            }

            // Regla 2: Segregación estricta líquidos vs papel higroscópico.
            if ($product->is_liquid && $pallet['has_hygroscopic']) {
                continue;
            }
            if ($product->is_hygroscopic && $pallet['has_liquids']) {
                continue;
            }

            // Verificar si cabe al menos 1 bulto respetando peso, volumen y camas operativas.
            $capacity = $this->computeAvailableBoxCapacity($pallet, $product, $lookahead);
            if ($capacity['max_boxes'] <= 0) {
                continue;
            }

            // Puntuación para favorecer estiba mixta estratificada equilibrada.
            $score = 100;

            if ($product->zone_priority === 3) {
                // Superior / Ligero prefiere rematar tarimas con base sólida (zona 1 o 2).
                if ($pallet['highest_zone_priority'] === 2) {
                    $score += 60;
                } elseif ($pallet['highest_zone_priority'] === 1) {
                    $score += 50;
                } else {
                    $score += 10;
                }
            } elseif ($product->zone_priority === 2) {
                // Intermedio prefiere apoyarse en Base (1) o continuar Intermedio (2).
                $score += $pallet['highest_zone_priority'] === 1 ? 55 : 40;
            } else {
                // Base / Pesado consolida con otras bases compatibles (o líquidos con líquidos).
                if ($product->is_liquid && $pallet['has_liquids']) {
                    $score += 70;
                } elseif (! $product->is_liquid && ! $pallet['has_liquids']) {
                    $score += 50;
                }
            }

            // Preferir llenar tarimas ya iniciadas antes de fragmentar.
            $score += (int) round(($pallet['volume_m3'] / Pallet::MAX_USABLE_VOLUME_M3) * 20);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $idx;
            }
        }

        return $bestIndex;
    }

    /**
     * Calcula cuántas cajas caben en una tarima bajo el doble bloqueo Poka-Yoke + camas operativas + reserva posterior.
     *
     * @param  array<string, mixed>  $pallet
     * @param  array<string, mixed>  $lookahead
     * @return array{max_boxes: int, limiting_factor: string, reserved_for_lookahead: bool}
     */
    private function computeAvailableBoxCapacity(array $pallet, Product $product, array $lookahead): array
    {
        $maxWeightAllowed = Pallet::MAX_TOTAL_WEIGHT_KG;
        $maxVolumeAllowed = Pallet::MAX_USABLE_VOLUME_M3;
        $reservedForLookahead = false;

        // Reserva predictiva: si es Base/Pesado no-líquido y hay carga Intermedio/Ligero programada,
        // reserva ~0.55 m³ superiores (o tope 760 kg) para permitir tarima mixta estratificada.
        if (
            $product->zone_priority === 1
            && ! $product->is_liquid
            && ($lookahead['pending_inter_boxes'] + $lookahead['pending_light_boxes']) > 0
            && $lookahead['pending_light_volume_m3'] >= 0.25
        ) {
            $maxVolumeAllowed = 1.40;
            $maxWeightAllowed = 780.0;
            $reservedForLookahead = true;
        }

        $remainingWeight = max(0.0, $maxWeightAllowed - (float) $pallet['total_weight_kg']);
        $remainingVolume = max(0.0, $maxVolumeAllowed - (float) $pallet['volume_m3']);

        $byWeight = (int) floor($remainingWeight / max(0.01, (float) $product->master_weight_kg));
        $byVolume = (int) floor($remainingVolume / max(0.0001, (float) $product->master_volume_m3));

        $boxesPerLayer = $product->boxesPerLayer();
        $maxBoxesSku = $boxesPerLayer * $product->max_layers_operational;
        $existingSkuBoxes = (int) ($pallet['sku_boxes'][$product->id] ?? 0);
        $byLayers = max(0, $maxBoxesSku - $existingSkuBoxes);

        $maxBoxes = min($byWeight, $byVolume, $byLayers);

        $limitingFactor = 'PESO_1000KG';
        if ($maxBoxes === $byLayers && $byLayers <= $byWeight && $byLayers <= $byVolume) {
            $limitingFactor = sprintf('CAMAS_MAX_%d_ECT_%d', $product->max_layers_operational, $product->ect_rating);
        } elseif ($maxBoxes === $byVolume && $byVolume < $byWeight) {
            $limitingFactor = $reservedForLookahead ? 'RESERVA_MIXTA_POSTERIOR' : 'VOLUMEN_1.95M3_ALTURA_1.85M';
        } elseif ($reservedForLookahead) {
            $limitingFactor = 'RESERVA_BASE_MIXTA_780KG';
        }

        return [
            'max_boxes' => max(0, $maxBoxes),
            'limiting_factor' => $limitingFactor,
            'reserved_for_lookahead' => $reservedForLookahead,
        ];
    }

    /**
     * Determina si la tarima ya alcanzó punto óptimo de cierre para subirla al Rack 4×8.
     *
     * @param  array<string, mixed>  $palletState
     * @param  array<string, mixed>  $lookahead
     */
    private function shouldAutoSlotState(
        array $palletState,
        Product $product,
        int $skuBoxesOnPallet,
        int $maxBoxesByLayerCap,
        array $lookahead
    ): bool {
        // Si tiene reserva para llegadas posteriores y aún está en zona Base, mantener abierta para recibir remate ligero.
        if (
            $palletState['highest_zone_priority'] === 1
            && ! $palletState['has_liquids']
            && ($lookahead['pending_inter_boxes'] + $lookahead['pending_light_boxes']) > 0
            && $palletState['total_weight_kg'] < 850.0
            && $palletState['volume_m3'] < 1.55
        ) {
            return false;
        }

        if ($palletState['total_weight_kg'] >= 880.0) {
            return true;
        }

        if ($palletState['volume_m3'] >= 1.68 || $palletState['estimated_height_m'] >= 1.72) {
            return true;
        }

        // Hiperdensos como OFI-006 (grapas 26/6) al llegar a 4 camas operativas (821.2 kg).
        if ($product->is_high_density_metal && $skuBoxesOnPallet >= $maxBoxesByLayerCap && $palletState['total_weight_kg'] >= 780.0) {
            return true;
        }

        return false;
    }

    /**
     * Algoritmo de asignación de coordenada (Nivel 1..4 × Posición 1..8) en el Rack de Sucursal.
     * - Nivel 1 = Piso pesado (>=750 kg o líquidos sin papel abajo).
     * - Posiciones P8→P5 = Ola 1 (próximo Tortón hacia andenes Este E1–E3).
     * - Posiciones P4→P1 = Ola 2 (llegadas posteriores sin obstruir salida inmediata).
     * - Poka-Yoke vertical: jamás líquidos arriba de papel higroscópico en la misma columna.
     *
     * @param  list<array{level: int, position: int, has_liquids: bool, has_hygroscopic: bool, wave_number: int}>  $slottedPallets
     * @return array{available: bool, level: int|null, position: int|null, wave_number: int, reason: string}
     */
    private function findOptimalRackSlotFromArray(
        array $slottedPallets,
        float $totalWeightKg,
        bool $hasLiquids,
        bool $hasHygroscopic,
        string $palletProfile
    ): array {
        $occupied = [];
        $wave1Count = 0;

        foreach ($slottedPallets as $p) {
            $occupied[$p['level']][$p['position']] = $p;
            if ($p['wave_number'] === 1) {
                $wave1Count++;
            }
        }

        $waveNumber = $wave1Count < 12 ? 1 : 2;

        // Orden de niveles por peso y seguridad estructural.
        if ($hasLiquids) {
            $levelOrder = [1, 2, 3, 4];
        } elseif ($totalWeightKg >= 750.0 || $palletProfile === 'base_pesada') {
            $levelOrder = [1, 2, 3, 4];
        } elseif ($totalWeightKg >= 450.0) {
            $levelOrder = [2, 1, 3, 4];
        } else {
            $levelOrder = [3, 4, 2, 1];
        }

        // Orden de posiciones por proximidad a andén de salida (P8=Este salida, P1=Oeste recibo).
        // Para líquidos: priorizar bahía segregada P1..P4 en Nivel 1 (sin papel debajo).
        // Para higroscópicos: reservar N1·P1 como último recurso para no bloquear la bahía de líquidos.
        if ($hasLiquids) {
            $posOrder = [1, 2, 3, 4, 5, 6, 7, 8];
        } else {
            $posOrder = $waveNumber === 1
                ? [8, 7, 6, 5, 4, 3, 2, 1]
                : [4, 3, 2, 1, 5, 6, 7, 8];
        }

        // Dos pasadas para higroscópicos: primero evitar N1·P1 (bahía de líquidos) y solo usarla si no hay otra opción.
        $passes = $hasHygroscopic ? [true, false] : [false];

        foreach ($passes as $reserveLiquidBay) {
            foreach ($levelOrder as $level) {
                foreach ($posOrder as $pos) {
                    if ($reserveLiquidBay && $level === 1 && $pos === 1) {
                        continue;
                    }

                    if (isset($occupied[$level][$pos])) {
                        continue;
                    }

                    // Poka-Yoke vertical en rack: nunca líquido encima de papel higroscópico.
                    if (! $this->isColumnSegregationSafe($occupied, $level, $pos, $hasLiquids, $hasHygroscopic)) {
                        continue;
                    }

                    $zoneLabel = $hasLiquids
                        ? 'Bahía Segregada de Líquidos (Piso / Sin papel debajo)'
                        : ($waveNumber === 1
                            ? 'Zona Este Ola 1 (próximo Tortón hacia E1–E3)'
                            : 'Zona Oeste Ola 2 (llegadas posteriores sin obstruir salida)');

                    $levelLabel = match ($level) {
                        1 => 'Nivel 1 Piso Pesado',
                        2 => 'Nivel 2 Estiba Media',
                        3 => 'Nivel 3 Estiba Ligera',
                        default => 'Nivel 4 Estiba Superior',
                    };

                    $safetyNote = $hasLiquids
                        ? 'Columna validada sin papelería higroscópica debajo.'
                        : ($hasHygroscopic ? 'Columna protegida sin líquidos arriba (45–55% HR sobre viga/tarima).' : 'Balance estructural verificado.');

                    return [
                        'available' => true,
                        'level' => $level,
                        'position' => $pos,
                        'wave_number' => $waveNumber,
                        'reason' => sprintf('%s · %s (%.1f kg). %s', $levelLabel, $zoneLabel, $totalWeightKg, $safetyNote),
                    ];
                }
            }
        }

        return [
            'available' => false,
            'level' => null,
            'position' => null,
            'wave_number' => $waveNumber,
            'reason' => 'Sin posiciones libres compatibles en el rack 4×8.',
        ];
    }

    /**
     * @param  array<int, array<int, array{level: int, position: int, has_liquids: bool, has_hygroscopic: bool, wave_number: int}>>  $occupied
     */
    private function isColumnSegregationSafe(
        array $occupied,
        int $targetLevel,
        int $targetPos,
        bool $hasLiquids,
        bool $hasHygroscopic
    ): bool {
        for ($lvl = 1; $lvl <= 4; $lvl++) {
            if (! isset($occupied[$lvl][$targetPos])) {
                continue;
            }

            $existing = $occupied[$lvl][$targetPos];

            // Si entra líquido arriba de higroscópico → BLOQUEADO.
            if ($hasLiquids && $lvl < $targetLevel && $existing['has_hygroscopic']) {
                return false;
            }

            // Si entra higroscópico abajo de líquido → BLOQUEADO.
            if ($hasHygroscopic && $lvl > $targetLevel && $existing['has_liquids']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $lookahead
     */
    private function initialPalletProfile(Product $product, array $lookahead): string
    {
        if ($product->is_liquid) {
            return 'liquidos_segregados';
        }

        if ($product->zone_priority === 3 && $lookahead['pending_base_boxes'] > 0) {
            return 'ligera_staging';
        }

        if ($product->zone_priority === 1) {
            return 'base_pesada';
        }

        return 'mixta_estratificada';
    }

    /**
     * @param  array<string, mixed>  $palletState
     */
    private function resolvePalletProfile(array $palletState): string
    {
        if ($palletState['has_liquids']) {
            return 'liquidos_segregados';
        }

        if ($palletState['highest_zone_priority'] === 3 && $palletState['total_weight_kg'] < 450.0) {
            return 'ligera_staging';
        }

        if ($palletState['highest_zone_priority'] === 1 && $palletState['total_weight_kg'] >= 720.0) {
            return 'base_pesada';
        }

        return 'mixta_estratificada';
    }

    /**
     * @param  array<string, mixed>  $lookahead
     * @param  array<string, mixed>  $palletState
     */
    private function buildPlacementReason(
        Product $product,
        bool $isNewPallet,
        string $limitingFactor,
        bool $reservedForLookahead,
        array $lookahead,
        array $palletState
    ): string {
        $parts = [];

        $parts[] = $isNewPallet
            ? sprintf('Abre tarima %s en zona %s (densidad %d kg/m³).', $palletState['code'], $product->stow_zone, $product->master_density_kg_m3)
            : sprintf('Consolida en %s en zona %s sin aplastar carga inferior.', $palletState['code'], $product->stow_zone);

        if ($product->is_liquid) {
            $parts[] = $product->orientation_rule === 'HORIZONTAL_TINTA'
                ? 'Segregada de papel · Estibar HORIZONTAL para tinta.'
                : 'Segregada de papel · Estibar VERTICAL (flechas arriba).';
        }

        if ($reservedForLookahead) {
            $parts[] = sprintf(
                'Reserva espacio superior para %d bultos Intermedio/Ligero programados.',
                $lookahead['pending_inter_boxes'] + $lookahead['pending_light_boxes']
            );
        }

        if (str_starts_with($limitingFactor, 'CAMAS_MAX_')) {
            $parts[] = sprintf('Tope operativo de %d camas (%d ECT) respetado.', $product->max_layers_operational, $product->ect_rating);
        }

        return implode(' ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluateErgonomics(Product $product, int $boxes, string $distanceBand, ?string $operatorName): array
    {
        $opName = $operatorName ?: 'Operador Turno CEDIS';
        $log = OperatorErgonomicLog::query()
            ->where('operator_name', $opName)
            ->where('shift_date', now()->toDateString())
            ->first();

        $currentMovedKg = $log ? (float) $log->moved_weight_kg : 0.0;
        $dailyLimitKg = $distanceBand === '10_to_20m'
            ? OperatorErgonomicLog::LIMIT_10_TO_20M_KG
            : OperatorErgonomicLog::LIMIT_UNDER_10M_KG;

        $batchWeightKg = round($boxes * $product->master_weight_kg, 2);
        $projectedMovedKg = round($currentMovedKg + $batchWeightKg, 2);

        return [
            'nom036_level' => $product->nom036_level,
            'alert_message' => $product->ergonomicAlertMessage(),
            'requires_auxiliary_equipment' => $product->master_weight_kg > 20.0,
            'exceeds_male_limit' => $product->master_weight_kg > 25.0,
            'operator_name' => $opName,
            'distance_band' => $distanceBand,
            'current_shift_kg' => $currentMovedKg,
            'batch_weight_kg' => $batchWeightKg,
            'projected_shift_kg' => $projectedMovedKg,
            'daily_limit_kg' => $dailyLimitKg,
            'projected_usage_pct' => round(($projectedMovedKg / $dailyLimitKg) * 100.0, 1),
            'exceeds_daily_quota' => $projectedMovedKg > $dailyLimitKg,
        ];
    }

    private function recordErgonomicActivity(
        Product $product,
        int $boxes,
        string $distanceBand,
        ?int $userId,
        string $operatorName
    ): OperatorErgonomicLog {
        $shiftDate = now()->toDateString();
        $log = OperatorErgonomicLog::query()->firstOrCreate(
            ['operator_name' => $operatorName, 'shift_date' => $shiftDate],
            [
                'user_id' => $userId,
                'distance_band' => $distanceBand,
                'moved_weight_kg' => 0.0,
                'boxes_moved' => 0,
                'assisted_moves' => 0,
            ]
        );

        $addedWeight = round($boxes * $product->master_weight_kg, 2);
        $assistedIncrement = $product->master_weight_kg > 20.0 ? $boxes : 0;

        $log->update([
            'user_id' => $userId ?? $log->user_id,
            'distance_band' => $distanceBand,
            'moved_weight_kg' => round($log->moved_weight_kg + $addedWeight, 2),
            'boxes_moved' => $log->boxes_moved + $boxes,
            'assisted_moves' => $log->assisted_moves + $assistedIncrement,
            'last_alert' => $product->ergonomicAlertMessage() ?? $log->last_alert,
        ]);

        return $log->fresh();
    }

    private function nextPalletSequenceNumber(Branch $branch): int
    {
        return $branch->pallets()->count() + 1;
    }

    private function generateNextPalletCode(Branch $branch): string
    {
        $seq = $this->nextPalletSequenceNumber($branch);
        $code = sprintf('TAR-%02d-%03d', $branch->number, $seq);

        while (Pallet::query()->where('code', $code)->exists()) {
            $seq++;
            $code = sprintf('TAR-%02d-%03d', $branch->number, $seq);
        }

        return $code;
    }

    private function determineNextWaveNumber(Branch $branch): int
    {
        $activeWave1 = $branch->pallets()
            ->whereIn('status', ['building', 'slotted'])
            ->where('wave_number', 1)
            ->count();

        return $activeWave1 < 12 ? 1 : 2;
    }
}
