<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\OutboundOrder;
use App\Models\Pallet;
use App\Models\PlannedArrival;
use App\Models\Product;
use App\Models\User;
use App\Services\OutboundOrderService;
use App\Services\SlottingEngineService;
use Database\Seeders\BranchSeeder;
use Database\Seeders\StationeryCatalogSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\WmsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WmsSlottingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            UserSeeder::class,
            BranchSeeder::class,
            StationeryCatalogSeeder::class,
        ]);
    }

    public function test_catalog_contains_all_72_audited_skus_with_exact_zone_distribution(): void
    {
        $products = Product::query()->orderBy('catalog_order')->get();

        $this->assertCount(72, $products);
        $this->assertSame(25, $products->where('zone_priority', 1)->count());
        $this->assertSame(38, $products->where('zone_priority', 2)->count());
        $this->assertSame(9, $products->where('zone_priority', 3)->count());

        // Verificar orden ascendente estricto por peso unitario (desempate por peso máster)
        $previousUnitWeight = 0.0;
        $previousMasterWeight = 0.0;
        foreach ($products as $product) {
            if ($product->unit_weight_g === $previousUnitWeight) {
                $this->assertGreaterThanOrEqual($previousMasterWeight, $product->master_weight_kg);
            } else {
                $this->assertGreaterThan($previousUnitWeight, $product->unit_weight_g);
            }
            $previousUnitWeight = $product->unit_weight_g;
            $previousMasterWeight = $product->master_weight_kg;
        }

        // Verificar recorte operativo OFI-006 (4 camas en vez de 6) y NOM-036 en PAP-007 / PAP-009
        $staples = Product::query()->where('sku', 'OFI-006')->firstOrFail();
        $this->assertSame(6, $staples->max_layers_ect);
        $this->assertSame(4, $staples->max_layers_operational);
        $this->assertSame(12, $staples->boxesPerLayer());

        $bondCarta = Product::query()->where('sku', 'PAP-007')->firstOrFail();
        $this->assertSame('alerta_20_25', $bondCarta->nom036_level);

        $bondOficio = Product::query()->where('sku', 'PAP-009')->firstOrFail();
        $this->assertSame('equipo_auxiliar_obligatorio', $bondOficio->nom036_level);
    }

    public function test_dual_poka_yoke_caps_hyperdense_staples_at_4_layers_below_1000_kg(): void
    {
        $engine = app(SlottingEngineService::class);
        $branch = Branch::query()->where('code', 'SUC-05')->firstOrFail();
        $staples = Product::query()->where('sku', 'OFI-006')->firstOrFail();

        $result = $engine->receiveBoxes($branch, $staples, 60, 'under_10m', null, 'Operador Test');

        $this->assertCount(2, $result['allocations']);
        $this->assertSame(48, $result['allocations'][0]['boxes_placed']);
        $this->assertEqualsWithDelta(821.2, $result['allocations'][0]['pallet_total_weight_kg'], 0.2);
        $this->assertTrue($result['allocations'][0]['auto_slotted']);
        $this->assertSame(12, $result['allocations'][1]['boxes_placed']);

        // Ninguna tarima puede superar 1,000 kg ni 1.95 m³
        foreach (Pallet::query()->where('branch_id', $branch->id)->get() as $pallet) {
            $this->assertLessThanOrEqual(1000.0, $pallet->total_weight_kg);
            $this->assertLessThanOrEqual(1.95, $pallet->volume_m3);
        }
    }

    public function test_prevents_crushing_light_cargo_and_enforces_liquid_hygroscopic_segregation(): void
    {
        $engine = app(SlottingEngineService::class);
        $branch = Branch::query()->where('code', 'SUC-06')->firstOrFail();
        $fomy = Product::query()->where('sku', 'ART-002')->firstOrFail();
        $bondCarta = Product::query()->where('sku', 'PAP-007')->firstOrFail();
        $resistol1kg = Product::query()->where('sku', 'ADH-012')->firstOrFail();

        // 1. Llega primero Fomy (Superior / Ligero)
        $resLight = $engine->receiveBoxes($branch, $fomy, 6, 'under_10m', null, 'Op', null, false);
        $lightPalletId = $resLight['allocations'][0]['pallet_id'];

        // 2. Luego llega Papel Bond Carta (Base / Pesado) — NO debe montarse encima del Fomy
        $resHeavy = $engine->receiveBoxes($branch, $bondCarta, 10, 'under_10m', null, 'Op', null, false);
        $heavyPalletId = $resHeavy['allocations'][0]['pallet_id'];
        $this->assertNotSame($lightPalletId, $heavyPalletId);

        // 3. Luego llega Resistol 850 1kg (Líquido Base) — NO debe mezclarse con la tarima higroscópica de Bond
        $resLiquid = $engine->receiveBoxes($branch, $resistol1kg, 10, 'under_10m', null, 'Op', null, false);
        $liquidPalletId = $resLiquid['allocations'][0]['pallet_id'];
        $this->assertNotSame($heavyPalletId, $liquidPalletId);
        $this->assertNotSame($lightPalletId, $liquidPalletId);

        // 4. Al ubicar en Rack 4×8, ningún líquido puede quedar arriba de un higroscópico en la misma columna
        $heavyPallet = Pallet::query()->findOrFail($heavyPalletId);
        $liquidPallet = Pallet::query()->findOrFail($liquidPalletId);
        $engine->slotPalletIntoRack($heavyPallet);
        $engine->slotPalletIntoRack($liquidPallet);

        $heavyPallet->refresh();
        $liquidPallet->refresh();

        if ($heavyPallet->rack_position === $liquidPallet->rack_position) {
            $this->assertLessThan($heavyPallet->rack_level, $liquidPallet->rack_level);
        } else {
            $this->assertNotSame($heavyPallet->rack_position, $liquidPallet->rack_position);
        }
    }

    public function test_scheduled_batch_executes_canonical_wms_ordering_and_nom036_tracking(): void
    {
        $user = User::query()->where('email', 'operador@cedis.com')->firstOrFail();
        $branch = Branch::query()->where('code', 'SUC-07')->firstOrFail();

        $fomy = Product::query()->where('sku', 'ART-002')->firstOrFail();      // Zona 3, dens 116
        $marker = Product::query()->where('sku', 'ESC-003')->firstOrFail();    // Zona 2, dens 373
        $bondCarta = Product::query()->where('sku', 'PAP-007')->firstOrFail(); // Zona 1, dens 726
        $staples = Product::query()->where('sku', 'OFI-006')->firstOrFail();   // Zona 1, dens 942

        foreach ([[$fomy, 6], [$marker, 10], [$bondCarta, 10], [$staples, 12]] as [$prod, $qty]) {
            PlannedArrival::create([
                'folio' => 'ARR-TEST-01',
                'branch_id' => $branch->id,
                'product_id' => $prod->id,
                'boxes_expected' => $qty,
                'boxes_received' => 0,
                'dock_code' => 'R2',
                'eta_window' => '12:00–13:00',
                'supplier_name' => 'Test',
                'status' => 'scheduled',
            ]);
        }

        $response = $this->actingAs($user)->postJson(route('wms.receive-batch'), [
            'branch_id' => $branch->id,
            'distance_band' => 'under_10m',
        ]);

        $response->assertOk();
        $steps = $response->json('batch.steps');

        $this->assertSame(['OFI-006', 'PAP-007', 'ESC-003', 'ART-002'], array_column($steps, 'sku'));
        $this->assertGreaterThan(0, $response->json('state.operator_log.moved_weight_kg'));
        $this->assertSame(10, $response->json('state.operator_log.assisted_moves'));
    }

    public function test_outbound_order_enforces_90_percent_lock_and_generates_qr_manifest(): void
    {
        $this->seed(WmsDemoSeeder::class);
        $user = User::query()->where('email', 'gerente@cedis.com')->firstOrFail();

        // 1. SUC-04 (<90%) debe bloquearse sin excepción
        $suc04 = Branch::query()->where('code', 'SUC-04')->firstOrFail();
        $blocked = $this->actingAs($user)->postJson(route('wms.outbound.store'), [
            'branch_id' => $suc04->id,
            'dock_code' => 'E1',
            'unit_plate' => '44-VR-1A',
            'driver_name' => 'Operador Test',
            'exception_authorized' => false,
        ]);
        $blocked->assertStatus(422);

        // 2. SUC-01 (≥90% peso y volumen) debe despachar, liberar 12 posiciones y renderizar manifiesto QR
        $suc01 = Branch::query()->where('code', 'SUC-01')->firstOrFail();
        $planBefore = app(OutboundOrderService::class)->evaluateBranchDispatchPlan($suc01);
        $this->assertTrue($planBefore['ready_for_torton']);
        $this->assertGreaterThanOrEqual(90.0, $planBefore['weight_pct']);
        $this->assertGreaterThanOrEqual(90.0, $planBefore['volume_pct']);

        $dispatched = $this->actingAs($user)->postJson(route('wms.outbound.store'), [
            'branch_id' => $suc01->id,
            'dock_code' => 'E1',
            'unit_plate' => '55-XA-2F',
            'driver_name' => 'Roberto Ruiz Hernández',
        ]);
        $dispatched->assertCreated();

        $orderId = $dispatched->json('order.id');
        $order = OutboundOrder::query()->findOrFail($orderId);
        $this->assertSame(12, $order->pallet_count);
        $this->assertSame(0, Pallet::query()->where('branch_id', $suc01->id)->where('status', 'slotted')->count());

        $manifestResponse = $this->actingAs($user)->get(route('wms.outbound.manifest', $order));
        $manifestResponse->assertOk();
        $manifestResponse->assertSee($order->folio);
        $manifestResponse->assertSee('data:image/svg+xml;base64,');
    }

    public function test_wms_page_and_existing_role_dashboards_render_cleanly(): void
    {
        $this->seed(WmsDemoSeeder::class);
        $gerente = User::query()->where('email', 'gerente@cedis.com')->firstOrFail();
        $jefeEmbarque = User::query()->where('email', 'embarque@cedis.com')->firstOrFail();
        $proveedor = User::query()->where('email', 'proveedor@cedis.com')->firstOrFail();

        $this->actingAs($gerente)->get(route('wms.index'))->assertOk()->assertSee('ERP ALMACÉN · CEDIS CDMX');
        $this->actingAs($gerente)->get(route('dashboard.gerente'))
            ->assertOk()
            ->assertSee('ERP DIRECCIÓN GENERAL · CEDIS CDMX')
            ->assertSee('Padrón de Entregas Programadas y Proveedores Activos');

        $this->actingAs($jefeEmbarque)->get(route('dashboard.embarques'))
            ->assertOk()
            ->assertSee('ERP EMBARQUES Y TRÁFICO · CEDIS CDMX')
            ->assertSee('Cola Priorizada por Fecha Compromiso y ≥90%')
            ->assertSee('Padrón de Entregas Programadas y Proveedores Activos');

        $this->actingAs($gerente)->get(route('dashboard.recepcion'))->assertOk()->assertSee('ERP RECEPCIÓN · TERMINAL ZEBRA TC22');

        // El proveedor externo NO debe ver el Padrón de Entregas Programadas
        $this->actingAs($proveedor)->get(route('dashboard.proveedor'))
            ->assertOk()
            ->assertSee('ERP PROVEEDORES · CEDIS CDMX')
            ->assertDontSee('Padrón de Entregas Programadas y Proveedores Activos');

        $this->actingAs($gerente)->get(route('dashboard.sucursal'))->assertOk()->assertSee('ERP DESTINO DE ENTREGA · CDMX');
    }

    public function test_can_register_new_product_with_auto_calculated_density_and_stow_zone(): void
    {
        $user = User::query()->where('email', 'gerente@cedis.com')->firstOrFail();

        $response = $this->actingAs($user)->postJson(route('wms.products.store'), [
            'sku' => 'PAP-011',
            'name' => 'Cuaderno Profesional Pasta Dura 150h',
            'category' => 'Papel y cuadernos',
            'commercial_presentation' => '1 pieza',
            'master_factor' => 24,
            'master_weight_kg' => 15.20,
            'master_length_cm' => 42.0,
            'master_width_cm' => 28.0,
            'master_height_cm' => 24.0,
            'is_liquid' => false,
            'is_hygroscopic' => true,
            'is_crushable' => false,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('product.sku', 'PAP-011');
        $response->assertJsonPath('product.zone_priority', 1);
        $response->assertJsonPath('product.stow_zone', 'Base / Pesado');
        $this->assertSame(73, Product::query()->count());
    }

    public function test_product_qr_label_generation_and_qr_scan_storage_for_cdmx_destinations(): void
    {
        $this->seed(WmsDemoSeeder::class);
        $user = User::query()->where('email', 'operador@cedis.com')->firstOrFail();

        $polanco = Branch::query()->where('code', 'SUC-02')->firstOrFail();
        $this->assertSame('Polanco', $polanco->name);

        $bondCarta = Product::query()->where('sku', 'PAP-007')->firstOrFail();

        // 1. Generar hoja de etiquetas QR para el producto
        $labelResp = $this->actingAs($user)->get(route('wms.qr.label', [
            'branch_id' => $polanco->id,
            'product_id' => $bondCarta->id,
            'boxes' => 4,
        ]));
        $labelResp->assertOk();
        $labelResp->assertSee('PAP-007');
        $labelResp->assertSee('Polanco');
        $labelResp->assertSee('data:image/svg+xml;base64,');

        // 2. Escanear QR de producto y verificar que registra el almacenamiento en pallet_items
        $qrPayload = json_encode([
            'bulto' => 'BLT-SUC-02-PAP-007-01',
            'folio' => 'QR-CDMX-01',
            'destino' => 'SUC-02',
            'sku' => 'PAP-007',
            'cajas' => 4,
            'piezas' => 40,
        ], JSON_UNESCAPED_UNICODE);

        $scanResp = $this->actingAs($user)->postJson(route('wms.qr.scan'), [
            'qr_payload' => $qrPayload,
            'distance_band' => 'under_10m',
        ]);

        $scanResp->assertOk();
        $scanResp->assertJsonPath('result.product_sku', 'PAP-007');
        $scanResp->assertJsonPath('result.boxes_processed', 4);
    }

    public function test_supplier_portal_can_schedule_delivery_and_send_arrivals_to_wms(): void
    {
        $user = User::query()->where('email', 'proveedor@cedis.com')->firstOrFail();
        $polanco = Branch::query()->where('code', 'SUC-02')->firstOrFail();
        $bondCarta = Product::query()->where('sku', 'PAP-007')->firstOrFail();

        $response = $this->actingAs($user)->postJson(route('wms.supplier.schedule'), [
            'oc' => 'OC-2026-0199',
            'invoice_folio' => 'FAC-DM-88421',
            'supplier_name' => 'Distribuidora Metropolitana S.A.',
            'selected_date' => '2026-11-26',
            'dock_code' => 'R4',
            'start_hour' => 14,
            'duration_hours' => 2,
            'packages' => [
                [
                    'product_id' => $bondCarta->id,
                    'branch_id' => $polanco->id,
                    'boxes' => 15,
                ],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('created_arrivals.0.sku', 'PAP-007');
        $response->assertJsonPath('created_arrivals.0.branch_code', 'SUC-02');
        $response->assertJsonPath('created_arrivals.0.boxes_expected', 15);
        $this->assertDatabaseHas('planned_arrivals', [
            'branch_id' => $polanco->id,
            'product_id' => $bondCarta->id,
            'boxes_expected' => 15,
            'dock_code' => 'R4',
        ]);
    }
}
