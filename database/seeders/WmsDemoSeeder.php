<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\OperatorErgonomicLog;
use App\Models\OutboundOrder;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\PlannedArrival;
use App\Models\Product;
use App\Services\SlottingEngineService;
use Illuminate\Database\Seeder;

class WmsDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Pallet::query()->exists() || PlannedArrival::query()->exists()) {
            return;
        }

        $this->seedFreshScenario();
    }

    /**
     * Reinicia y siembra un escenario operativo realista de CEDIS de papelería:
     * - SUC-01 (Centro Histórico): 12 tarimas mixtas estratificadas listas para Tortón (≥90% peso y volumen) + llegadas Ola 2.
     * - SUC-02 (Polanco): 9 tarimas en rack + 2 tarimas abiertas + llegadas programadas mixtas para demostrar acomodo predictivo.
     * - SUC-03 (Coyoacán): 12 tarimas listas para Tortón (≥90%) + 1 tarima de líquidos segregada en Nivel 1.
     * - SUC-04 (Iztapalapa Central): 5 tarimas en rack + lote completo de llegadas pendientes listo para ejecutar el algoritmo WMS en 1 clic.
     */
    public function seedFreshScenario(): void
    {
        PalletItem::query()->delete();
        Pallet::query()->delete();
        PlannedArrival::query()->delete();
        OutboundOrder::query()->delete();
        OperatorErgonomicLog::query()->delete();

        $engine = app(SlottingEngineService::class);
        $products = Product::query()->get()->keyBy('sku');
        $branches = Branch::query()->get()->keyBy('code');

        if ($products->isEmpty() || $branches->isEmpty()) {
            return;
        }

        // 1. SUC-01 Centro Histórico: 12 tarimas mixtas estratificadas listas para salida Tortón (≥90% peso y volumen)
        $suc01 = $branches->get('SUC-01');
        if ($suc01 !== null) {
            for ($i = 1; $i <= 10; $i++) {
                $this->buildAndSlotMixedPallet($engine, $suc01, [
                    [$products['PAP-007'], 24], // 568.8 kg · 0.7824 m³ (Base / Pesado)
                    [$products['PAP-004'], 12], // 164.16 kg · 0.3384 m³ (Base / Pesado)
                    [$products['ESC-010'], 12], // 114.96 kg · 0.2952 m³ (Intermedio)
                    [$products['ARC-011'], 8],  // 72.48 kg · 0.4600 m³ (Superior / Ligero)
                ]);
            }
            // 2 tarimas más: 1 metálica/oficina + 1 líquidos segregada en Nivel 1
            $this->buildAndSlotMixedPallet($engine, $suc01, [
                [$products['OFI-006'], 40], // 666 kg · 0.708 m³ (Grapas 26/6 dentro de 4 camas!)
                [$products['OFI-001'], 18], // 134.46 kg · 0.459 m³ (Intermedio)
                [$products['ART-002'], 11], // 79.64 kg · 0.6842 m³ (Fomy Superior / Ligero)
            ]);
            $this->buildAndSlotMixedPallet($engine, $suc01, [
                [$products['ADH-012'], 40], // 548 kg · 0.872 m³ (Resistol 850 1kg - Líquido Base)
                [$products['ADH-009'], 30], // 221.4 kg · 0.531 m³ (Resistol 250g - Líquido Intermedio)
                [$products['ART-007'], 16], // 137.44 kg · 0.2208 m³ (Pintura acrílica - Líquido Intermedio)
            ]);

            // Llegadas posteriores (Ola 2) programadas para SUC-01
            $this->scheduleArrival($suc01, $products['PAP-009'], 20, 'ARR-2026-011', 'R1', 'Hoy 13:00–14:30', 'Copamex Papelero S.A.');
            $this->scheduleArrival($suc01, $products['ARC-010'], 16, 'ARR-2026-011', 'R1', 'Hoy 13:00–14:30', 'Copamex Papelero S.A.');
        }

        // 2. SUC-02 Polanco: 8 tarimas en rack + 1 tarima abierta en armado + llegadas mixtas pendientes
        $suc02 = $branches->get('SUC-02');
        if ($suc02 !== null) {
            for ($i = 1; $i <= 8; $i++) {
                $this->buildAndSlotMixedPallet($engine, $suc02, [
                    [$products['PAP-006'], 30], // Opalina Base
                    [$products['ESC-001'], 24], // Lápices Intermedio
                    [$products['ARC-008'], 12], // Carpetas 1" Superior
                ]);
            }

            // Dejar 1 tarima en armado con Base lista para demostrar remate superior
            $engine->receiveBoxes($suc02, $products['PAP-007'], 20, 'under_10m', null, 'Juan Pérez Gómez', null, false);

            // Llegadas programadas que activan el Acomodo Predictivo
            $this->scheduleArrival($suc02, $products['PAP-009'], 16, 'ARR-2026-021', 'R2', 'Hoy 12:00–13:30', 'Distribuidora Papelera Metropolitana');
            $this->scheduleArrival($suc02, $products['OFI-006'], 48, 'ARR-2026-021', 'R2', 'Hoy 12:00–13:30', 'Distribuidora Papelera Metropolitana');
            $this->scheduleArrival($suc02, $products['ADH-012'], 18, 'ARR-2026-022', 'R3', 'Hoy 14:00–15:00', 'Henkel Adhesivos México');
            $this->scheduleArrival($suc02, $products['ESC-007'], 20, 'ARR-2026-022', 'R3', 'Hoy 14:00–15:00', 'Azor México S.A.');
            $this->scheduleArrival($suc02, $products['ART-002'], 15, 'ARR-2026-023', 'R3', 'Hoy 15:00–16:00', 'Barrilito Escolar');
            $this->scheduleArrival($suc02, $products['ARC-011'], 14, 'ARR-2026-023', 'R3', 'Hoy 15:00–16:00', 'Oxford Archivo');
        }

        // 3. SUC-03 Coyoacán: 12 tarimas listas para Tortón (≥90%)
        $suc03 = $branches->get('SUC-03');
        if ($suc03 !== null) {
            for ($i = 1; $i <= 12; $i++) {
                $this->buildAndSlotMixedPallet($engine, $suc03, [
                    [$products['PAP-006'], 36], // Opalina 225g Base (628.2 kg · 0.9252 m³)
                    [$products['PAP-001'], 18], // Post-it Intermedio (169.02 kg · 0.3186 m³)
                    [$products['COR-008'], 10], // Juegos geometría Superior (111.8 kg · 0.5530 m³)
                ]);
            }
        }

        // 4. SUC-04 Iztapalapa Central: 5 tarimas en rack + lote completo listo para secuenciación WMS
        $suc04 = $branches->get('SUC-04');
        if ($suc04 !== null) {
            for ($i = 1; $i <= 5; $i++) {
                $this->buildAndSlotMixedPallet($engine, $suc04, [
                    [$products['ARC-007'], 28], // Broches Baco Base (560 kg)
                    [$products['ADH-008'], 16], // Cinta empaque Intermedio
                    [$products['OFI-005'], 12], // Calculadoras Casio Superior
                ]);
            }

            $this->scheduleArrival($suc04, $products['ARC-011'], 12, 'ARR-2026-041', 'R4', 'Hoy 11:30–13:00', 'Suministros Escolares Golfo');
            $this->scheduleArrival($suc04, $products['ESC-003'], 18, 'ARR-2026-041', 'R4', 'Hoy 11:30–13:00', 'Suministros Escolares Golfo');
            $this->scheduleArrival($suc04, $products['PAP-007'], 28, 'ARR-2026-041', 'R4', 'Hoy 11:30–13:00', 'Suministros Escolares Golfo');
            $this->scheduleArrival($suc04, $products['OFI-006'], 56, 'ARR-2026-041', 'R4', 'Hoy 11:30–13:00', 'Suministros Escolares Golfo');
            $this->scheduleArrival($suc04, $products['ART-002'], 10, 'ARR-2026-041', 'R4', 'Hoy 11:30–13:00', 'Suministros Escolares Golfo');
        }

        // Reiniciar el contador ergonómico de hoy a un nivel realista de arranque de turno (1,420 kg movidos)
        OperatorErgonomicLog::query()->updateOrCreate(
            ['operator_name' => 'Juan Pérez Gómez', 'shift_date' => now()->toDateString()],
            [
                'distance_band' => 'under_10m',
                'moved_weight_kg' => 1420.5,
                'boxes_moved' => 112,
                'assisted_moves' => 8,
                'last_alert' => 'Turno iniciado dentro de cuota NOM-036-1-STPS-2018 (10,000 kg <10 m).',
            ]
        );
    }

    /**
     * @param  list<array{Product, int}>  $recipe
     */
    private function buildAndSlotMixedPallet(SlottingEngineService $engine, Branch $branch, array $recipe): void
    {
        $pallet = Pallet::create([
            'code' => sprintf('TAR-%02d-%03d', $branch->number, $branch->pallets()->count() + 1),
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
            'pallet_profile' => 'mixta_estratificada',
            'wave_number' => 1,
        ]);

        $goodsWeight = 0.0;
        $volume = 0.0;
        $boxCount = 0;
        $highestZone = 0;
        $hasLiquids = false;
        $hasHygroscopic = false;

        foreach ($recipe as [$product, $qty]) {
            $w = round($qty * $product->master_weight_kg, 2);
            $v = round($qty * $product->master_volume_m3, 4);

            PalletItem::create([
                'pallet_id' => $pallet->id,
                'product_id' => $product->id,
                'batch_code' => 'INIT-SEED',
                'quantity_boxes' => $qty,
                'assigned_zone_priority' => $product->zone_priority,
                'layer_index' => (int) ceil($qty / $product->boxesPerLayer()),
                'total_weight_kg' => $w,
                'total_volume_m3' => $v,
                'orientation_instruction' => $product->orientation_rule,
                'placement_reason' => 'Estiba mixta estratificada inicial validada por motor WMS.',
                'ergonomic_warning' => $product->ergonomicAlertMessage(),
            ]);

            $goodsWeight = round($goodsWeight + $w, 2);
            $volume = round($volume + $v, 4);
            $boxCount += $qty;
            $highestZone = max($highestZone, $product->zone_priority);
            $hasLiquids = $hasLiquids || $product->is_liquid;
            $hasHygroscopic = $hasHygroscopic || $product->is_hygroscopic;
        }

        $totalWeight = round(Pallet::WOOD_TARE_KG + $goodsWeight, 2);
        $pallet->update([
            'goods_weight_kg' => $goodsWeight,
            'total_weight_kg' => $totalWeight,
            'volume_m3' => $volume,
            'estimated_height_m' => $engine->estimatePalletHeightM($volume),
            'box_count' => $boxCount,
            'highest_zone_priority' => $highestZone,
            'has_liquids' => $hasLiquids,
            'has_hygroscopic' => $hasHygroscopic,
            'pallet_profile' => $hasLiquids ? 'liquidos_segregados' : 'mixta_estratificada',
        ]);

        $engine->slotPalletIntoRack($pallet->fresh());
    }

    private function scheduleArrival(
        Branch $branch,
        Product $product,
        int $boxes,
        string $folio,
        string $dock,
        string $window,
        string $supplier
    ): void {
        PlannedArrival::create([
            'folio' => $folio,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'boxes_expected' => $boxes,
            'boxes_received' => 0,
            'dock_code' => $dock,
            'eta_window' => $window,
            'supplier_name' => $supplier,
            'status' => 'scheduled',
        ]);
    }
}
