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
use App\Services\WarehouseSlottingTopologyGuard;
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
        $manifestResponse->assertSee('ETIQUETA MAESTRA DE EMBARQUE FINAL TORTÓN');
        $manifestResponse->assertSee('Desglose Consolidado del Contenido Embarcado');
        $manifestResponse->assertDontSee('Etiquetas Individuales por Caja del Embarque');
        $manifestResponse->assertSee('PAP-007');
        $manifestResponse->assertSee('MCH-SUC-01-');
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
            ->assertSee('Padrón de Entregas Programadas y Proveedores Activos')
            ->assertDontSee('Orden de Compra')
            ->assertDontSee('Cotización');

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

        // RBAC estricto: proveedor no puede entrar a /gerente ni a /acomodo sin cerrar sesión
        $this->actingAs($proveedor)->get(route('dashboard.gerente'))
            ->assertRedirect(route('dashboard.proveedor'));
        $this->actingAs($proveedor)->get(route('wms.index'))
            ->assertRedirect(route('dashboard.proveedor'));

        // La ruta rápida por puesto ya no debe existir
        $this->get('/login/quick/gerente')->assertNotFound();

        $sucursal = User::query()->where('email', 'sucursal@cedis.com')->firstOrFail();
        $this->actingAs($sucursal)->get(route('dashboard.sucursal'))->assertOk()->assertSee('ERP DESTINO DE ENTREGA · CDMX');
    }

    public function test_each_role_authenticates_with_its_own_email_and_password(): void
    {
        $credentials = [
            ['gerente@cedis.com', 'Gerente#2026', '/gerente'],
            ['embarque@cedis.com', 'Embarque#2026', '/embarques'],
            ['operador@cedis.com', 'Operador#2026', '/recepcion'],
            ['proveedor@cedis.com', 'Proveedor#2026', '/proveedor'],
            ['sucursal@cedis.com', 'Sucursal#2026', '/sucursal'],
        ];

        foreach ($credentials as [$email, $password, $expectedPath]) {
            $loginResponse = $this->post('/login', [
                'email' => $email,
                'password' => $password,
            ]);
            $loginResponse->assertRedirect($expectedPath);
            $this->assertAuthenticated();

            $this->post('/logout')->assertRedirect('/');
            $this->assertGuest();
        }
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
            'asn_folio' => 'ASN-2026-0199',
            'remission_folio' => 'REM-DM-88421',
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

    public function test_dual_label_workflow_from_supplier_integrated_qr_to_operator_continuous_laser_label(): void
    {
        $proveedor = User::query()->where('email', 'proveedor@cedis.com')->firstOrFail();
        $operador = User::query()->where('email', 'operador@cedis.com')->firstOrFail();
        $coyoacan = Branch::query()->where('code', 'SUC-03')->firstOrFail();
        $bondCarta = Product::query()->where('sku', 'PAP-007')->firstOrFail();

        // 1. El proveedor registra sus artículos y recibe automáticamente la Etiqueta #1 (QR Integrado de Origen)
        $scheduleResp = $this->actingAs($proveedor)->postJson(route('wms.supplier.schedule'), [
            'asn_folio' => 'ASN-2026-0210',
            'remission_folio' => 'REM-PRV-5501',
            'supplier_name' => 'Papelera Industrial Azteca S.A.',
            'selected_date' => '2026-11-27',
            'dock_code' => 'R3',
            'start_hour' => 11,
            'duration_hours' => 2,
            'packages' => [
                [
                    'product_id' => $bondCarta->id,
                    'branch_id' => $coyoacan->id,
                    'boxes' => 3,
                ],
            ],
        ]);

        $scheduleResp->assertCreated();
        $arrivalData = $scheduleResp->json('created_arrivals.0');
        $this->assertNotEmpty($arrivalData['sample_bulto_code']);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $arrivalData['sample_qr_svg']);

        // La planilla imprimible de etiquetas integradas del proveedor (1 Principal del Conjunto + 1 por Caja)
        $this->actingAs($proveedor)
            ->get($scheduleResp->json('batch_qr_label_url'))
            ->assertOk()
            ->assertSee('ETIQUETA PRINCIPAL DEL CONJUNTO')
            ->assertSee('CAJA 1 / 3')
            ->assertSee('CAJA 3 / 3')
            ->assertSee('Papelera Industrial Azteca S.A.');

        // 2. El operador ya tiene en /recepcion el registro vivo de qué va a llegar, dónde (R3) y cuándo (11:00–13:00)
        $this->actingAs($operador)
            ->get(route('dashboard.recepcion'))
            ->assertOk()
            ->assertSee('Papelera Industrial Azteca S.A.')
            ->assertSee('R3')
            ->assertSee('2026-11-27 · 11:00–13:00')
            ->assertSee('PAP-007');

        // 3. El operador escanea el QR de la etiqueta integrada del proveedor para comprobar la llegada
        $scanResp = $this->actingAs($operador)->postJson(route('wms.qr.scan'), [
            'qr_payload' => $arrivalData['sample_qr_payload'],
        ]);

        $scanResp->assertOk();
        $scanResp->assertJsonPath('arrival_updated.boxes_received', 1);
        $scanResp->assertJsonPath('arrival_updated.pending_boxes', 2);

        // 4. Una vez comprobado, se genera automáticamente la Etiqueta #2 (Láser Continua: Acomodo + Origen + Salida)
        $laserLabel = $scanResp->json('laser_labels.0');
        $this->assertStringStartsWith('LSR-03-', $laserLabel['internal_label_code']);
        $this->assertStringContainsString('RACK-03', $laserLabel['rack_coordinate']);
        $this->assertSame('Papelera Industrial Azteca S.A.', $laserLabel['origin_supplier']);
        $this->assertSame('R3', $laserLabel['origin_dock']);
        $this->assertStringContainsString('SUC-03', $laserLabel['outbound_branch']);
        $this->assertStringContainsString('ANDÉN E', $laserLabel['outbound_dock_target']);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $laserLabel['qr_data_uri']);

        // 5. No permite escanear dos veces el mismo bulto comprobado
        $duplicateResp = $this->actingAs($operador)->postJson(route('wms.qr.scan'), [
            'qr_payload' => $arrivalData['sample_qr_payload'],
        ]);
        $duplicateResp->assertStatus(422);

        // 6. El operador abre la tira continua de etiquetas láser (1 Principal del Conjunto + 1 por cada Caja)
        $laserSheetResp = $this->actingAs($operador)->get($scanResp->json('continuous_print_url'));
        $laserSheetResp->assertOk()
            ->assertSee('ETIQUETA PRINCIPAL DEL CONJUNTO COMPROBADO')
            ->assertSee('ETIQUETA INTERNA DE ACOMODO Y SALIDA (LÁSER CONTINUO)')
            ->assertSee('CAJA 1 / 1')
            ->assertSee($laserLabel['internal_label_code'])
            ->assertSee('Papelera Industrial Azteca S.A.')
            ->assertSee('RACK-03')
            ->assertSee('SUC-03');
    }

    public function test_gerente_can_create_and_classify_internal_and_external_users_and_non_gerente_is_blocked(): void
    {
        $gerente = User::query()->where('email', 'gerente@cedis.com')->firstOrFail();
        $operador = User::query()->where('email', 'operador@cedis.com')->firstOrFail();

        // 1. Un operador NO puede crear usuarios
        $this->actingAs($operador)->postJson(route('gerente.users.store'), [
            'name' => 'Intento No Autorizado',
            'email' => 'fail@cedis.com',
            'password' => 'Secret#2026',
            'role' => 'gerente',
        ])->assertForbidden();

        // 2. El gerente crea un nuevo Gerente interno y un Proveedor externo
        $newGerenteResp = $this->actingAs($gerente)->postJson(route('gerente.users.store'), [
            'name' => 'Ing. Sofía Delgado',
            'email' => 'gerente2@cedis.com',
            'password' => 'Sofia#2026',
            'role' => 'gerente',
            'job_title' => 'Subgerente de Operaciones CEDIS',
        ]);
        $newGerenteResp->assertCreated();
        $newGerenteResp->assertJsonPath('user.classification', 'INTERNO CEDIS');

        $newProveedorResp = $this->actingAs($gerente)->postJson(route('gerente.users.store'), [
            'name' => 'Papelera del Valle S.A.',
            'email' => 'valle@proveedor.mx',
            'password' => 'Valle#2026',
            'role' => 'proveedor',
            'supplier_rfc' => 'PVA990101XY9',
        ]);
        $newProveedorResp->assertCreated();
        $newProveedorResp->assertJsonPath('user.classification', 'EXTERNO');
        $newProveedorResp->assertJsonPath('user.supplier_rfc', 'PVA990101XY9');

        // 3. El nuevo proveedor externo puede iniciar sesión de inmediato
        $this->post('/logout');
        $this->post('/login', [
            'email' => 'valle@proveedor.mx',
            'password' => 'Valle#2026',
        ])->assertRedirect('/proveedor');

        // 4. El gerente suspende la cuenta externa y ya no puede iniciar sesión
        $this->post('/logout');
        $createdUserId = $newProveedorResp->json('user.id');
        $this->actingAs($gerente)->patchJson(route('gerente.users.toggle', $createdUserId))
            ->assertOk()
            ->assertJsonPath('user.is_active', false);

        $this->post('/logout');
        $this->post('/login', [
            'email' => 'valle@proveedor.mx',
            'password' => 'Valle#2026',
        ])->assertSessionHasErrors('email');
    }

    public function test_warehouse_slotting_topology_guard_blocks_all_6_violation_categories_with_422_and_audit_trail(): void
    {
        WarehouseSlottingTopologyGuard::clearViolations();

        $operador = User::query()->where('email', 'operador@cedis.com')->firstOrFail();
        $suc01 = Branch::query()->where('code', 'SUC-01')->firstOrFail();
        $suc02 = Branch::query()->where('code', 'SUC-02')->firstOrFail();

        $bondCarta = Product::query()->where('sku', 'PAP-007')->firstOrFail();  // 23.7 kg, Base, Higroscópico
        $bondOficio = Product::query()->where('sku', 'PAP-009')->firstOrFail(); // 28.4 kg (>25 kg NOM-036)
        $resistol = Product::query()->where('sku', 'ADH-012')->firstOrFail();   // Líquido Base
        $fomy = Product::query()->where('sku', 'ART-002')->firstOrFail();       // Superior / Ligero

        // Crear una tarima abierta en SUC-01 con Fomy (Superior / Ligero)
        $lightReceive = $this->actingAs($operador)->postJson(route('wms.receive'), [
            'branch_id' => $suc01->id,
            'product_id' => $fomy->id,
            'boxes' => 4,
            'auto_slot_when_full' => false,
        ])->assertOk();
        $lightPalletId = (int) $lightReceive->json('result.allocations.0.pallet_id');

        // 1. AISLAMIENTO POR SUCURSAL: Intentar meter bultos de SUC-02 en la tarima de SUC-01 → 422
        $crossBranchResp = $this->actingAs($operador)->postJson(route('wms.receive'), [
            'branch_id' => $suc02->id,
            'product_id' => $fomy->id,
            'boxes' => 2,
            'target_pallet_id' => $lightPalletId,
        ]);
        $crossBranchResp->assertStatus(422)
            ->assertJsonFragment(['message' => $crossBranchResp->json('message')]);
        $this->assertStringContainsString('AISLAMIENTO DE SUCURSAL VIOLADO', (string) $crossBranchResp->json('message'));

        // 5. INTEGRIDAD VERTICAL (NO APLASTAMIENTO): Intentar forzar Papel Bond (Zona 1 Base) sobre la tarima con Fomy (Zona 3 Ligero) → 422
        $crushResp = $this->actingAs($operador)->postJson(route('wms.receive'), [
            'branch_id' => $suc01->id,
            'product_id' => $bondCarta->id,
            'boxes' => 4,
            'target_pallet_id' => $lightPalletId,
        ]);
        $crushResp->assertStatus(422);
        $this->assertStringContainsString('RIESGO DE APLASTAMIENTO', (string) $crushResp->json('message'));

        // 3. POKA-YOKE SOBREPESO (>1,000 kg): Intentar forzar 50 cajas de Papel Bond Carta (1,185 kg + 22 kg tara) en una misma tarima → 422
        $paperReceive = $this->actingAs($operador)->postJson(route('wms.receive'), [
            'branch_id' => $suc01->id,
            'product_id' => $bondCarta->id,
            'boxes' => 10,
            'auto_slot_when_full' => false,
        ])->assertOk();
        $paperPalletId = (int) $paperReceive->json('result.allocations.0.pallet_id');

        $overweightResp = $this->actingAs($operador)->postJson(route('wms.receive'), [
            'branch_id' => $suc01->id,
            'product_id' => $bondCarta->id,
            'boxes' => 40,
            'target_pallet_id' => $paperPalletId,
        ]);
        $overweightResp->assertStatus(422);

        // 4a. SEGREGACIÓN EN TARIMA: Intentar meter líquido (Resistol ADH-012) en la tarima de papel higroscópico → 422
        $liquidMixResp = $this->actingAs($operador)->postJson(route('wms.receive'), [
            'branch_id' => $suc01->id,
            'product_id' => $resistol->id,
            'boxes' => 2,
            'target_pallet_id' => $paperPalletId,
        ]);
        $liquidMixResp->assertStatus(422);
        $this->assertStringContainsString('SEGREGACIÓN QUÍMICA/HIGROSCÓPICA VIOLADA', (string) $liquidMixResp->json('message'));

        // 2. VALIDACIÓN TOPOLÓGICA DE COORDENADAS (4×8): Intentar ubicar tarima en Nivel 5 o Posición 9 → 422
        $outOfBoundsResp = $this->actingAs($operador)->postJson(route('wms.pallets.slot', $paperPalletId), [
            'target_level' => 5,
            'target_position' => 9,
        ]);
        $outOfBoundsResp->assertStatus(422);
        $this->assertStringContainsString('COORDENADA FUERA DE RANGO (4×8)', (string) $outOfBoundsResp->json('message'));

        // 4b. SEGREGACIÓN VERTICAL EN RACK 4×8: Ubicar papel en N1·P4 y luego intentar forzar líquido encima en N2·P4 → 422
        $this->actingAs($operador)->postJson(route('wms.pallets.slot', $paperPalletId), [
            'target_level' => 1,
            'target_position' => 4,
        ])->assertOk();

        $liquidReceive = $this->actingAs($operador)->postJson(route('wms.receive'), [
            'branch_id' => $suc01->id,
            'product_id' => $resistol->id,
            'boxes' => 10,
            'auto_slot_when_full' => false,
        ])->assertOk();
        $liquidPalletId = (int) $liquidReceive->json('result.allocations.0.pallet_id');

        $liquidAbovePaperResp = $this->actingAs($operador)->postJson(route('wms.pallets.slot', $liquidPalletId), [
            'target_level' => 2,
            'target_position' => 4,
        ]);
        $liquidAbovePaperResp->assertStatus(422);
        $this->assertStringContainsString('SEGREGACIÓN VERTICAL VIOLADA', (string) $liquidAbovePaperResp->json('message'));

        // 6. CUMPLIMIENTO NOM-036 (>25 kg sin equipo auxiliar) y NOM-006 (N2–N4 sin maquinaria) → 422
        $nom036Resp = $this->actingAs($operador)->postJson(route('wms.receive'), [
            'branch_id' => $suc01->id,
            'product_id' => $bondOficio->id, // 28.4 kg (>25 kg)
            'boxes' => 2,
            'auxiliary_equipment_confirmed' => false,
        ]);
        $nom036Resp->assertStatus(422);
        $this->assertStringContainsString('BLOQUEO NOM-036-1-STPS-2018', (string) $nom036Resp->json('message'));

        $nom006Resp = $this->actingAs($operador)->postJson(route('wms.pallets.slot', $lightPalletId), [
            'target_level' => 3,
            'target_position' => 5,
            'machinery_confirmed' => false,
        ]);
        $nom006Resp->assertStatus(422);
        $this->assertStringContainsString('BLOQUEO NOM-006-STPS-2023', (string) $nom006Resp->json('message'));

        // Verificar que todas las violaciones quedaron registradas en la bitácora de auditoría del Guard
        $violations = WarehouseSlottingTopologyGuard::recentViolations();
        $this->assertGreaterThanOrEqual(7, count($violations));
    }
}
