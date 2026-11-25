<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\OperatorErgonomicLog;
use App\Models\OutboundOrder;
use App\Models\Pallet;
use App\Models\PlannedArrival;
use App\Models\Product;
use App\Services\OutboundOrderService;
use App\Services\SlottingEngineService;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Database\Seeders\WmsDemoSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

class SlottingController extends Controller
{
    public function __construct(
        private readonly SlottingEngineService $slottingEngine,
        private readonly OutboundOrderService $outboundService,
    ) {}

    public function index(Request $request): View
    {
        $branches = Branch::query()->orderBy('number')->get();
        $selectedCode = (string) $request->query('branch', 'SUC-02');
        $selectedBranch = $branches->firstWhere('code', $selectedCode) ?? $branches->firstOrFail();

        $products = Product::query()->orderBy('catalog_order')->get();
        $defaultProduct = $products->firstWhere('sku', 'PAP-007') ?? $products->first();
        $initialRecommendation = $defaultProduct !== null
            ? $this->slottingEngine->recommendPlacement(
                $selectedBranch,
                $defaultProduct,
                10,
                'under_10m',
                Auth::user()?->name ?? 'Juan Pérez Gómez'
            )
            : null;

        $state = $this->buildWorkspacePayload($selectedBranch);

        return view('wms.acomodo', [
            'user' => Auth::user(),
            'branchesSummary' => $this->buildBranchesSummary($branches),
            'products' => $products,
            'initialBranchCode' => $selectedBranch->code,
            'initialState' => $state,
            'initialRecommendation' => $initialRecommendation,
        ]);
    }

    public function state(Branch $branch): JsonResponse
    {
        $branches = Branch::query()->orderBy('number')->get();

        return response()->json([
            'state' => $this->buildWorkspacePayload($branch),
            'branchesSummary' => $this->buildBranchesSummary($branches),
        ]);
    }

    public function recommend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'boxes' => ['required', 'integer', 'min:1', 'max:500'],
            'distance_band' => ['nullable', 'in:under_10m,10_to_20m'],
        ]);

        $branch = Branch::query()->findOrFail($validated['branch_id']);
        $product = Product::query()->findOrFail($validated['product_id']);
        $operatorName = Auth::user()?->name ?? 'Juan Pérez Gómez';

        $recommendation = $this->slottingEngine->recommendPlacement(
            $branch,
            $product,
            (int) $validated['boxes'],
            $validated['distance_band'] ?? 'under_10m',
            $operatorName
        );

        return response()->json($recommendation);
    }

    public function receive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'boxes' => ['required', 'integer', 'min:1', 'max:500'],
            'distance_band' => ['nullable', 'in:under_10m,10_to_20m'],
            'planned_arrival_id' => ['nullable', 'integer', 'exists:planned_arrivals,id'],
            'auto_slot_when_full' => ['nullable', 'boolean'],
        ]);

        $branch = Branch::query()->findOrFail($validated['branch_id']);
        $product = Product::query()->findOrFail($validated['product_id']);
        $arrival = isset($validated['planned_arrival_id'])
            ? PlannedArrival::query()->find($validated['planned_arrival_id'])
            : null;

        try {
            $result = $this->slottingEngine->receiveBoxes(
                $branch,
                $product,
                (int) $validated['boxes'],
                $validated['distance_band'] ?? 'under_10m',
                Auth::id(),
                Auth::user()?->name ?? 'Juan Pérez Gómez',
                $arrival,
                (bool) ($validated['auto_slot_when_full'] ?? true)
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $branches = Branch::query()->orderBy('number')->get();

        return response()->json([
            'result' => $result,
            'state' => $this->buildWorkspacePayload($branch->fresh()),
            'branchesSummary' => $this->buildBranchesSummary($branches),
        ]);
    }

    public function receiveBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'distance_band' => ['nullable', 'in:under_10m,10_to_20m'],
        ]);

        $branch = Branch::query()->findOrFail($validated['branch_id']);

        try {
            $result = $this->slottingEngine->receiveScheduledBatch(
                $branch,
                $validated['distance_band'] ?? 'under_10m',
                Auth::id(),
                Auth::user()?->name ?? 'Juan Pérez Gómez'
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $branches = Branch::query()->orderBy('number')->get();

        return response()->json([
            'batch' => $result,
            'state' => $this->buildWorkspacePayload($branch->fresh()),
            'branchesSummary' => $this->buildBranchesSummary($branches),
        ]);
    }

    public function slotPallet(Pallet $pallet): JsonResponse
    {
        try {
            $assignment = $this->slottingEngine->slotPalletIntoRack($pallet);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $branch = $pallet->branch->fresh();
        $branches = Branch::query()->orderBy('number')->get();

        return response()->json([
            'assignment' => $assignment,
            'state' => $this->buildWorkspacePayload($branch),
            'branchesSummary' => $this->buildBranchesSummary($branches),
        ]);
    }

    public function storePlannedArrival(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'boxes_expected' => ['required', 'integer', 'min:1', 'max:500'],
            'dock_code' => ['required', 'in:R1,R2,R3,R4'],
            'eta_window' => ['required', 'string', 'max:60'],
            'supplier_name' => ['nullable', 'string', 'max:120'],
        ]);

        $branch = Branch::query()->findOrFail($validated['branch_id']);
        $nextSeq = PlannedArrival::query()->count() + 1;

        $arrival = PlannedArrival::create([
            'folio' => sprintf('ARR-2026-%03d', $nextSeq),
            'branch_id' => $branch->id,
            'product_id' => $validated['product_id'],
            'boxes_expected' => (int) $validated['boxes_expected'],
            'boxes_received' => 0,
            'dock_code' => $validated['dock_code'],
            'eta_window' => $validated['eta_window'],
            'supplier_name' => $validated['supplier_name'] ?: 'Proveedor Papelero Autorizado',
            'status' => 'scheduled',
        ]);

        $branches = Branch::query()->orderBy('number')->get();

        return response()->json([
            'arrival' => $arrival->load('product'),
            'state' => $this->buildWorkspacePayload($branch),
            'branchesSummary' => $this->buildBranchesSummary($branches),
        ], 201);
    }

    public function storeOutboundOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'dock_code' => ['required', 'in:E1,E2,E3'],
            'unit_plate' => ['required', 'string', 'max:24'],
            'driver_name' => ['required', 'string', 'max:120'],
            'exception_authorized' => ['nullable', 'boolean'],
            'exception_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $branch = Branch::query()->findOrFail($validated['branch_id']);

        try {
            $order = $this->outboundService->dispatchOutboundOrder(
                $branch,
                $validated['dock_code'],
                $validated['unit_plate'],
                $validated['driver_name'],
                (bool) ($validated['exception_authorized'] ?? false),
                $validated['exception_reason'] ?? null,
                Auth::id()
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $branches = Branch::query()->orderBy('number')->get();

        return response()->json([
            'order' => $order,
            'manifest_url' => route('wms.outbound.manifest', $order),
            'state' => $this->buildWorkspacePayload($branch->fresh()),
            'branchesSummary' => $this->buildBranchesSummary($branches),
        ], 201);
    }

    public function manifest(OutboundOrder $outboundOrder): View
    {
        $outboundOrder->load(['branch', 'pallets.items.product', 'createdBy']);

        $payload = json_encode([
            'folio' => $outboundOrder->folio,
            'sucursal' => $outboundOrder->branch->code,
            'anden' => $outboundOrder->dock_code,
            'placas' => $outboundOrder->unit_plate,
            'tarimas' => $outboundOrder->pallet_count,
            'peso_kg' => $outboundOrder->total_weight_kg,
            'volumen_m3' => $outboundOrder->total_volume_m3,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $qrSvgDataUri = (new QRCode(new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => true,
            'scale' => 6,
            'quietzoneSize' => 2,
        ])))->render($payload);

        return view('wms.manifest', [
            'order' => $outboundOrder,
            'qrSvgDataUri' => $qrSvgDataUri,
        ]);
    }

    /**
     * Genera etiqueta(s) QR industrial(es) para bultos de cualquiera de los 72 SKUs o de una llegada programada.
     */
    public function productQrLabel(Request $request): View
    {
        $arrivalId = $request->query('arrival_id');
        if ($arrivalId !== null) {
            $arrival = PlannedArrival::query()->with(['branch', 'product'])->findOrFail((int) $arrivalId);
            $branch = $arrival->branch;
            $product = $arrival->product;
            $boxes = max(1, $arrival->pendingBoxes());
            $folio = $arrival->folio;
            $dock = $arrival->dock_code;
            $supplier = $arrival->supplier_name;
        } else {
            $branch = Branch::query()->findOrFail((int) $request->query('branch_id', 1));
            $product = Product::query()->findOrFail((int) $request->query('product_id', 1));
            $boxes = max(1, min(100, (int) $request->query('boxes', 1)));
            $folio = 'ETQ-'.now()->format('His');
            $dock = 'R2';
            $supplier = 'Proveedor Papelero Autorizado CDMX';
        }

        $qrOptions = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => true,
            'scale' => 6,
            'quietzoneSize' => 2,
        ]);

        // Generamos etiqueta maestra del lote + etiquetas individuales (máx 12 en vista previa imprimible)
        $labels = [];
        $previewCount = min($boxes, 12);

        for ($i = 1; $i <= $previewCount; $i++) {
            $bultoCode = sprintf('BLT-%s-%s-%02d', $branch->code, $product->sku, $i);
            $payloadArray = [
                'bulto' => $bultoCode,
                'folio' => $folio,
                'destino' => $branch->code,
                'sku' => $product->sku,
                'cajas' => 1,
                'piezas' => $product->master_factor,
                'peso_kg' => $product->master_weight_kg,
                'zona' => $product->stow_zone,
            ];
            $payloadJson = json_encode($payloadArray, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            $labels[] = [
                'index' => $i,
                'total' => $boxes,
                'bulto_code' => $bultoCode,
                'payload_json' => $payloadJson,
                'qr_data_uri' => (new QRCode($qrOptions))->render($payloadJson),
            ];
        }

        $batchPayload = json_encode([
            'bulto' => sprintf('LOTE-%s-%s', $branch->code, $product->sku),
            'folio' => $folio,
            'destino' => $branch->code,
            'sku' => $product->sku,
            'cajas' => $boxes,
            'piezas' => $boxes * $product->master_factor,
            'peso_kg' => round($boxes * $product->master_weight_kg, 2),
            'zona' => $product->stow_zone,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $batchQrDataUri = (new QRCode($qrOptions))->render($batchPayload);

        return view('wms.qr-label', [
            'branch' => $branch,
            'product' => $product,
            'boxes' => $boxes,
            'folio' => $folio,
            'dock' => $dock,
            'supplier' => $supplier,
            'batchPayload' => $batchPayload,
            'batchQrDataUri' => $batchQrDataUri,
            'labels' => $labels,
        ]);
    }

    /**
     * Procesa el escaneo o lectura de un QR de bulto/lote, valida el SKU contra el catálogo real de 72 productos
     * y registra su almacenamiento en tarima y rack dentro del CEDIS CDMX.
     */
    public function scanQr(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_payload' => ['required', 'string'],
            'distance_band' => ['nullable', 'in:under_10m,10_to_20m'],
        ]);

        $decoded = json_decode($validated['qr_payload'], true);
        if (! is_array($decoded)) {
            return response()->json([
                'message' => 'El código QR no contiene un JSON válido del estándar CEDIS CDMX.',
            ], 422);
        }

        $sku = trim((string) ($decoded['sku'] ?? ''));
        $branchCode = trim((string) ($decoded['destino'] ?? $decoded['sucursal'] ?? ''));
        $boxes = max(1, (int) ($decoded['cajas'] ?? 1));
        $folio = trim((string) ($decoded['folio'] ?? $decoded['oc'] ?? ''));

        $product = Product::query()->where('sku', $sku)->first();
        if ($product === null) {
            return response()->json([
                'message' => "SKU '{$sku}' rechazado: no existe en el catálogo maestro auditado de 72 productos de papelería.",
            ], 422);
        }

        $branch = Branch::query()->where('code', $branchCode)->first();
        if ($branch === null) {
            return response()->json([
                'message' => "Destino '{$branchCode}' inválido: debe ser una de las 32 sucursales destino (SUC-01 a SUC-32).",
            ], 422);
        }

        $plannedArrival = null;
        if ($folio !== '') {
            $plannedArrival = PlannedArrival::query()
                ->where('branch_id', $branch->id)
                ->where('product_id', $product->id)
                ->where('folio', $folio)
                ->whereIn('status', ['scheduled', 'partial'])
                ->first();
        }

        try {
            $result = $this->slottingEngine->receiveBoxes(
                $branch,
                $product,
                $boxes,
                $validated['distance_band'] ?? 'under_10m',
                Auth::id(),
                Auth::user()?->name ?? 'Juan Pérez Gómez',
                $plannedArrival,
                true
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $branches = Branch::query()->orderBy('number')->get();

        return response()->json([
            'scanned_bulto' => $decoded['bulto'] ?? $folio,
            'result' => $result,
            'state' => $this->buildWorkspacePayload($branch->fresh()),
            'branchesSummary' => $this->buildBranchesSummary($branches),
        ]);
    }

    /**
     * Registra un nuevo producto en el catálogo maestro calculando automáticamente su densidad,
     * zona vertical de estiba, grado ECT, camas operativas y clasificación NOM-036.
     */
    public function storeProduct(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:20', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:180'],
            'category' => ['required', 'string', 'max:60'],
            'commercial_presentation' => ['nullable', 'string', 'max:80'],
            'master_factor' => ['required', 'integer', 'min:1', 'max:10000'],
            'master_weight_kg' => ['required', 'numeric', 'min:0.5', 'max:45'],
            'master_length_cm' => ['required', 'numeric', 'min:10', 'max:120'],
            'master_width_cm' => ['required', 'numeric', 'min:10', 'max:120'],
            'master_height_cm' => ['required', 'numeric', 'min:5', 'max:100'],
            'is_liquid' => ['nullable', 'boolean'],
            'is_hygroscopic' => ['nullable', 'boolean'],
            'is_crushable' => ['nullable', 'boolean'],
        ]);

        $weightKg = round((float) $validated['master_weight_kg'], 2);
        $lengthCm = round((float) $validated['master_length_cm'], 2);
        $widthCm = round((float) $validated['master_width_cm'], 2);
        $heightCm = round((float) $validated['master_height_cm'], 2);
        $factor = (int) $validated['master_factor'];

        $volumeM3 = round(($lengthCm * $widthCm * $heightCm) / 1000000.0, 4);
        $volumeM3 = max(0.0050, $volumeM3);
        $density = (int) round($weightKg / $volumeM3);
        $unitWeightG = round(($weightKg * 1000.0) / max(1, $factor), 2);

        $isLiquid = (bool) ($validated['is_liquid'] ?? false);
        $isHygroscopic = (bool) ($validated['is_hygroscopic'] ?? false);
        $isCrushable = (bool) ($validated['is_crushable'] ?? false);

        if ($isCrushable || $density < 240) {
            $stowZone = 'Superior / Ligero';
            $zonePriority = 3;
        } elseif ($weightKg >= 12.5 || $density >= 600) {
            $stowZone = 'Base / Pesado';
            $zonePriority = 1;
        } else {
            $stowZone = 'Intermedio / Estándar';
            $zonePriority = 2;
        }

        $ectRating = match (true) {
            $weightKg > 25.0 => 40,
            $weightKg >= 10.0 || $zonePriority === 1 => 32,
            $zonePriority === 3 && $weightKg < 8.0 => 26,
            default => 29,
        };

        $maxLayersEct = $zonePriority === 3 ? 4 : ($weightKg > 22.0 ? 4 : 5);
        $tempProduct = new Product([
            'master_length_cm' => $lengthCm,
            'master_width_cm' => $widthCm,
        ]);
        $boxesPerLayer = $tempProduct->boxesPerLayer();
        $maxLayersByWeight = max(1, (int) floor(978.0 / max(1.0, $boxesPerLayer * $weightKg)));
        $maxLayersOperational = max(1, min($maxLayersEct, $maxLayersByWeight));

        $nom036Level = match (true) {
            $weightKg > 25.0 => 'equipo_auxiliar_obligatorio',
            $weightKg > 20.0 => 'alerta_20_25',
            $weightKg > 15.0 => 'precaucion_15_20',
            default => 'estandar',
        };

        $riskProfile = match (true) {
            $isLiquid => 'liquido',
            $isHygroscopic => 'higroscopico',
            $isCrushable => 'aplastamiento',
            default => 'estandar',
        };

        $orientationRule = $isLiquid ? 'VERTICAL_FLECHAS_ARRIBA' : 'ESTANDAR';
        $nextOrder = ((int) Product::query()->max('catalog_order')) + 1;

        $product = Product::create([
            'catalog_order' => $nextOrder,
            'sku' => strtoupper(trim($validated['sku'])),
            'name' => trim($validated['name']),
            'category' => $validated['category'],
            'commercial_presentation' => $validated['commercial_presentation'] ?: '1 pieza',
            'unit_weight_g' => $unitWeightG,
            'unit_length_cm' => round($lengthCm / 2, 2),
            'unit_width_cm' => round($widthCm / 2, 2),
            'unit_height_cm' => round($heightCm / 2, 2),
            'unit_volume_cm3' => round(($volumeM3 * 1000000.0) / max(1, $factor), 2),
            'master_factor' => $factor,
            'master_weight_kg' => $weightKg,
            'master_length_cm' => $lengthCm,
            'master_width_cm' => $widthCm,
            'master_height_cm' => $heightCm,
            'master_volume_m3' => $volumeM3,
            'master_density_kg_m3' => $density,
            'ect_rating' => $ectRating,
            'max_layers_ect' => $maxLayersEct,
            'max_layers_operational' => $maxLayersOperational,
            'stow_zone' => $stowZone,
            'zone_priority' => $zonePriority,
            'risk_profile' => $riskProfile,
            'is_liquid' => $isLiquid,
            'is_hygroscopic' => $isHygroscopic,
            'is_temp_sensitive' => false,
            'is_crushable' => $isCrushable,
            'is_high_density_metal' => $density >= 700,
            'orientation_rule' => $orientationRule,
            'nom036_level' => $nom036Level,
            'handling_notes' => 'Producto registrado desde el asistente WMS.',
        ]);

        return response()->json([
            'product' => $product,
            'products' => Product::query()->orderBy('catalog_order')->get(),
        ], 201);
    }

    /**
     * Registra una entrega programada desde el Portal de Proveedor Externo (/proveedor)
     * creando las partidas en planned_arrivals y devolviendo sus enlaces de etiquetas QR reales.
     */
    public function storeSupplierDelivery(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'oc' => ['required', 'string', 'max:40'],
            'invoice_folio' => ['required', 'string', 'min:4', 'max:40'],
            'supplier_name' => ['required', 'string', 'max:120'],
            'selected_date' => ['required', 'string', 'max:20'],
            'dock_code' => ['required', 'in:R1,R2,R3,R4'],
            'start_hour' => ['required', 'integer', 'min:8', 'max:17'],
            'duration_hours' => ['required', 'integer', 'min:1', 'max:3'],
            'packages' => ['required', 'array', 'min:1'],
            'packages.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'packages.*.branch_id' => ['required', 'integer', 'exists:branches,id'],
            'packages.*.boxes' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $startHour = (int) $validated['start_hour'];
        $endHour = $startHour + (int) $validated['duration_hours'];
        $etaWindow = sprintf('%s · %02d:00–%02d:00', $validated['selected_date'], $startHour, $endHour);
        $folioBase = strtoupper(trim($validated['oc'])).' · '.strtoupper(trim($validated['invoice_folio']));

        $created = [];
        foreach ($validated['packages'] as $pkg) {
            $arrival = PlannedArrival::create([
                'folio' => substr($folioBase, 0, 32),
                'branch_id' => (int) $pkg['branch_id'],
                'product_id' => (int) $pkg['product_id'],
                'boxes_expected' => (int) $pkg['boxes'],
                'boxes_received' => 0,
                'dock_code' => $validated['dock_code'],
                'eta_window' => $etaWindow,
                'supplier_name' => trim($validated['supplier_name']),
                'status' => 'scheduled',
            ]);
            $arrival->load(['branch', 'product']);

            $created[] = [
                'id' => $arrival->id,
                'folio' => $arrival->folio,
                'branch_code' => $arrival->branch->code,
                'branch_name' => $arrival->branch->name,
                'rack_code' => $arrival->branch->rack_code,
                'sku' => $arrival->product->sku,
                'product_name' => $arrival->product->name,
                'stow_zone' => $arrival->product->stow_zone,
                'boxes_expected' => $arrival->boxes_expected,
                'total_pieces' => $arrival->boxes_expected * $arrival->product->master_factor,
                'total_weight_kg' => round($arrival->boxes_expected * $arrival->product->master_weight_kg, 2),
                'dock_code' => $arrival->dock_code,
                'eta_window' => $arrival->eta_window,
                'supplier_name' => $arrival->supplier_name,
                'qr_label_url' => route('wms.qr.label', ['arrival_id' => $arrival->id], false),
                'acomodo_url' => route('wms.index', ['branch' => $arrival->branch->code], false),
            ];
        }

        return response()->json([
            'message' => sprintf('Entrega registrada con éxito (%d partidas enviadas al CEDIS CDMX y etiquetas QR generadas).', count($created)),
            'created_arrivals' => $created,
        ], 201);
    }

    public function resetDemo(Request $request): JsonResponse
    {
        app(WmsDemoSeeder::class)->seedFreshScenario();

        $branchCode = (string) $request->input('branch_code', 'SUC-02');
        $branches = Branch::query()->orderBy('number')->get();
        $branch = $branches->firstWhere('code', $branchCode) ?? $branches->firstOrFail();

        return response()->json([
            'message' => 'Escenario demo WMS reiniciado con éxito.',
            'state' => $this->buildWorkspacePayload($branch),
            'branchesSummary' => $this->buildBranchesSummary($branches),
        ]);
    }

    /**
     * Construye el estado vivo completo para la sucursal seleccionada en el módulo WMS.
     *
     * @return array<string, mixed>
     */
    private function buildWorkspacePayload(Branch $branch): array
    {
        $openPallets = $branch->pallets()
            ->with(['items.product'])
            ->where('status', 'building')
            ->orderBy('id')
            ->get()
            ->map(fn (Pallet $p): array => $this->formatPallet($p))
            ->values()
            ->all();

        $slottedPallets = $branch->pallets()
            ->with(['items.product'])
            ->where('status', 'slotted')
            ->orderBy('rack_level')
            ->orderBy('rack_position')
            ->get();

        $rackGrid = [];
        foreach ($slottedPallets as $pallet) {
            $key = sprintf('%d-%d', (int) $pallet->rack_level, (int) $pallet->rack_position);
            $rackGrid[$key] = $this->formatPallet($pallet);
        }

        $plannedArrivals = $branch->plannedArrivals()
            ->with('product')
            ->whereIn('status', ['scheduled', 'partial'])
            ->join('products', 'planned_arrivals.product_id', '=', 'products.id')
            ->orderBy('products.zone_priority', 'asc')
            ->orderBy('products.master_density_kg_m3', 'desc')
            ->orderBy('products.master_weight_kg', 'desc')
            ->select('planned_arrivals.*')
            ->get()
            ->map(static fn (PlannedArrival $a): array => [
                'id' => $a->id,
                'folio' => $a->folio,
                'product_id' => $a->product_id,
                'sku' => $a->product?->sku,
                'product_name' => $a->product?->name,
                'stow_zone' => $a->product?->stow_zone,
                'zone_priority' => $a->product?->zone_priority,
                'master_weight_kg' => $a->product?->master_weight_kg,
                'master_density_kg_m3' => $a->product?->master_density_kg_m3,
                'is_liquid' => (bool) $a->product?->is_liquid,
                'is_hygroscopic' => (bool) $a->product?->is_hygroscopic,
                'orientation_rule' => $a->product?->orientation_rule,
                'boxes_expected' => $a->boxes_expected,
                'boxes_received' => $a->boxes_received,
                'pending_boxes' => $a->pendingBoxes(),
                'pending_weight_kg' => round($a->pendingBoxes() * (float) ($a->product?->master_weight_kg ?? 0), 1),
                'dock_code' => $a->dock_code,
                'eta_window' => $a->eta_window,
                'supplier_name' => $a->supplier_name,
                'status' => $a->status,
            ])
            ->values()
            ->all();

        $dispatchPlan = $this->outboundService->evaluateBranchDispatchPlan($branch);

        $outboundOrders = OutboundOrder::query()
            ->with('branch')
            ->latest('id')
            ->limit(25)
            ->get()
            ->map(static fn (OutboundOrder $o): array => [
                'id' => $o->id,
                'folio' => $o->folio,
                'branch_code' => $o->branch->code,
                'branch_name' => $o->branch->name,
                'dock_code' => $o->dock_code,
                'unit_plate' => $o->unit_plate,
                'driver_name' => $o->driver_name,
                'pallet_count' => $o->pallet_count,
                'total_weight_kg' => $o->total_weight_kg,
                'weight_pct' => $o->weight_pct,
                'total_volume_m3' => $o->total_volume_m3,
                'volume_pct' => $o->volume_pct,
                'exception_authorized' => $o->exception_authorized,
                'exception_reason' => $o->exception_reason,
                'dispatched_at' => $o->dispatched_at?->format('H:i · d/m/Y') ?? '',
                'manifest_url' => route('wms.outbound.manifest', $o),
            ])
            ->values()
            ->all();

        $operatorName = Auth::user()?->name ?? 'Juan Pérez Gómez';
        $ergonomicLog = OperatorErgonomicLog::query()
            ->where('operator_name', $operatorName)
            ->where('shift_date', now()->toDateString())
            ->first();

        return [
            'branch' => [
                'id' => $branch->id,
                'number' => $branch->number,
                'code' => $branch->code,
                'name' => $branch->name,
                'zone' => $branch->zone,
                'distance_km' => $branch->distance_km,
                'rack_code' => $branch->rack_code,
            ],
            'open_pallets' => $openPallets,
            'rack_grid' => $rackGrid,
            'slotted_count' => $slottedPallets->count(),
            'planned_arrivals' => $plannedArrivals,
            'lookahead' => $this->slottingEngine->analyzePendingArrivals($branch),
            'dispatch_plan' => $dispatchPlan,
            'outbound_orders' => $outboundOrders,
            'operator_log' => [
                'operator_name' => $operatorName,
                'distance_band' => $ergonomicLog?->distance_band ?? 'under_10m',
                'moved_weight_kg' => $ergonomicLog?->moved_weight_kg ?? 0.0,
                'boxes_moved' => $ergonomicLog?->boxes_moved ?? 0,
                'assisted_moves' => $ergonomicLog?->assisted_moves ?? 0,
                'daily_limit_kg' => $ergonomicLog?->dailyLimitKg() ?? OperatorErgonomicLog::LIMIT_UNDER_10M_KG,
                'usage_pct' => $ergonomicLog?->usagePct() ?? 0.0,
                'last_alert' => $ergonomicLog?->last_alert,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPallet(Pallet $pallet): array
    {
        return [
            'id' => $pallet->id,
            'code' => $pallet->code,
            'status' => $pallet->status,
            'rack_level' => $pallet->rack_level,
            'rack_position' => $pallet->rack_position,
            'coordinate' => $pallet->rackCoordinate(),
            'tare_weight_kg' => $pallet->tare_weight_kg,
            'goods_weight_kg' => $pallet->goods_weight_kg,
            'total_weight_kg' => $pallet->total_weight_kg,
            'weight_pct' => round(($pallet->total_weight_kg / Pallet::MAX_TOTAL_WEIGHT_KG) * 100.0, 1),
            'volume_m3' => $pallet->volume_m3,
            'volume_pct' => round(($pallet->volume_m3 / Pallet::MAX_USABLE_VOLUME_M3) * 100.0, 1),
            'estimated_height_m' => $pallet->estimated_height_m,
            'envelope_volume_m3' => $this->outboundService->palletEnvelopeVolumeM3($pallet),
            'box_count' => $pallet->box_count,
            'highest_zone_priority' => $pallet->highest_zone_priority,
            'has_liquids' => $pallet->has_liquids,
            'has_hygroscopic' => $pallet->has_hygroscopic,
            'pallet_profile' => $pallet->pallet_profile,
            'wave_number' => $pallet->wave_number,
            'items' => $pallet->items->sortBy('assigned_zone_priority')->values()->map(static fn ($item): array => [
                'id' => $item->id,
                'sku' => $item->product?->sku,
                'name' => $item->product?->name,
                'stow_zone' => $item->product?->stow_zone,
                'zone_priority' => $item->assigned_zone_priority,
                'quantity_boxes' => $item->quantity_boxes,
                'layer_index' => $item->layer_index,
                'total_weight_kg' => $item->total_weight_kg,
                'total_volume_m3' => $item->total_volume_m3,
                'master_density_kg_m3' => $item->product?->master_density_kg_m3,
                'is_liquid' => (bool) $item->product?->is_liquid,
                'is_hygroscopic' => (bool) $item->product?->is_hygroscopic,
                'orientation_instruction' => $item->orientation_instruction,
                'placement_reason' => $item->placement_reason,
                'ergonomic_warning' => $item->ergonomic_warning,
            ])->all(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Branch>  $branches
     * @return list<array<string, mixed>>
     */
    private function buildBranchesSummary(\Illuminate\Support\Collection $branches): array
    {
        $summary = [];

        foreach ($branches as $branch) {
            $plan = $this->outboundService->evaluateBranchDispatchPlan($branch);
            $buildingCount = $branch->pallets()->where('status', 'building')->count();
            $pendingCount = $branch->plannedArrivals()->whereIn('status', ['scheduled', 'partial'])->count();

            $summary[] = [
                'id' => $branch->id,
                'number' => $branch->number,
                'code' => $branch->code,
                'name' => $branch->name,
                'zone' => $branch->zone,
                'rack_code' => $branch->rack_code,
                'slotted_count' => $plan['slotted_total_count'],
                'building_count' => $buildingCount,
                'pending_arrivals_count' => $pendingCount,
                'weight_kg' => $plan['total_weight_kg'],
                'weight_pct' => $plan['weight_pct'],
                'volume_m3' => $plan['total_volume_m3'],
                'volume_pct' => $plan['volume_pct'],
                'ready_for_torton' => $plan['ready_for_torton'],
            ];
        }

        return $summary;
    }
}
