<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\OperatorErgonomicLog;
use App\Models\OutboundOrder;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\PlannedArrival;
use App\Models\Product;
use App\Services\OutboundOrderService;
use App\Services\SlottingEngineService;
use chillerlan\QRCode\Common\EccLevel;
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
            'target_pallet_id' => ['nullable', 'integer', 'exists:pallets,id'],
            'auxiliary_equipment_confirmed' => ['nullable', 'boolean'],
        ]);

        $branch = Branch::query()->findOrFail($validated['branch_id']);
        $product = Product::query()->findOrFail($validated['product_id']);
        $arrival = isset($validated['planned_arrival_id'])
            ? PlannedArrival::query()->find($validated['planned_arrival_id'])
            : null;
        $targetPallet = isset($validated['target_pallet_id'])
            ? Pallet::query()->find($validated['target_pallet_id'])
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
                (bool) ($validated['auto_slot_when_full'] ?? true),
                null,
                null,
                null,
                $targetPallet,
                (bool) ($validated['auxiliary_equipment_confirmed'] ?? true)
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

    public function slotPallet(Request $request, Pallet $pallet): JsonResponse
    {
        $validated = $request->validate([
            'target_level' => ['nullable', 'integer'],
            'target_position' => ['nullable', 'integer'],
            'target_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'machinery_confirmed' => ['nullable', 'boolean'],
        ]);

        $explicitBranch = isset($validated['target_branch_id'])
            ? Branch::query()->findOrFail((int) $validated['target_branch_id'])
            : null;

        try {
            $assignment = $this->slottingEngine->slotPalletIntoRack(
                $pallet,
                isset($validated['target_level']) ? (int) $validated['target_level'] : null,
                isset($validated['target_position']) ? (int) $validated['target_position'] : null,
                $explicitBranch,
                (bool) ($validated['machinery_confirmed'] ?? true)
            );
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
        $nextSeq = ((int) PlannedArrival::query()->max('id')) + 1;

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

        $skuGroups = [];
        $totalBoxes = 0;
        $totalPieces = 0;
        $hasLiquids = false;
        $hasHygroscopic = false;
        $suppliers = [];

        foreach ($outboundOrder->pallets as $pallet) {
            foreach ($pallet->items as $item) {
                $product = $item->product;
                if ($product === null) {
                    continue;
                }
                $sku = $product->sku;
                $boxes = (int) $item->quantity_boxes;
                $pieces = $boxes * (int) $product->master_factor;
                $totalBoxes += $boxes;
                $totalPieces += $pieces;
                $hasLiquids = $hasLiquids || (bool) $product->is_liquid;
                $hasHygroscopic = $hasHygroscopic || (bool) $product->is_hygroscopic;

                $supplierName = $item->origin_supplier ?: 'Proveedor Autorizado CDMX';
                $suppliers[$supplierName] = true;

                if (! isset($skuGroups[$sku])) {
                    $skuGroups[$sku] = [
                        'sku' => $sku,
                        'name' => $product->name,
                        'category' => $product->category,
                        'stow_zone' => $product->stow_zone,
                        'zone_priority' => $product->zone_priority,
                        'orientation_rule' => $product->orientation_rule,
                        'is_liquid' => (bool) $product->is_liquid,
                        'is_hygroscopic' => (bool) $product->is_hygroscopic,
                        'boxes' => 0,
                        'pieces' => 0,
                        'weight_kg' => 0.0,
                        'volume_m3' => 0.0,
                        'pallets' => [],
                        'suppliers' => [],
                    ];
                }

                $skuGroups[$sku]['boxes'] += $boxes;
                $skuGroups[$sku]['pieces'] += $pieces;
                $skuGroups[$sku]['weight_kg'] = round($skuGroups[$sku]['weight_kg'] + (float) $item->total_weight_kg, 2);
                $skuGroups[$sku]['volume_m3'] = round($skuGroups[$sku]['volume_m3'] + (float) $item->total_volume_m3, 3);
                $skuGroups[$sku]['pallets'][$pallet->code] = true;
                $skuGroups[$sku]['suppliers'][$supplierName] = true;
            }
        }

        $skuSummary = collect($skuGroups)
            ->sortBy([['zone_priority', 'asc'], ['weight_kg', 'desc']])
            ->map(static function (array $row): array {
                $row['pallets_list'] = implode(', ', array_keys($row['pallets']));
                $row['suppliers_list'] = implode(', ', array_keys($row['suppliers']));
                unset($row['pallets'], $row['suppliers']);

                return $row;
            })
            ->values();

        $sealCode = sprintf('MCH-%s-%04d', $outboundOrder->branch->code, $outboundOrder->id);

        $totals = [
            'seal_code' => $sealCode,
            'total_boxes' => $totalBoxes,
            'total_pieces' => $totalPieces,
            'distinct_skus' => $skuSummary->count(),
            'has_liquids' => $hasLiquids,
            'has_hygroscopic' => $hasHygroscopic,
            'suppliers_list' => implode(' · ', array_keys($suppliers)),
        ];

        $payload = json_encode([
            'folio' => $outboundOrder->folio,
            'marchamo' => $sealCode,
            'sucursal' => $outboundOrder->branch->code,
            'destino' => $outboundOrder->branch->name,
            'anden' => $outboundOrder->dock_code,
            'placas' => $outboundOrder->unit_plate,
            'tarimas' => $outboundOrder->pallet_count,
            'cajas' => $totalBoxes,
            'piezas' => $totalPieces,
            'skus' => $skuSummary->count(),
            'peso_kg' => $outboundOrder->total_weight_kg,
            'volumen_m3' => $outboundOrder->total_volume_m3,
            'liquidos' => $hasLiquids,
            'contenido' => $skuSummary->take(8)->map(static fn (array $r): string => $r['sku'].':'.$r['boxes'].'cja')->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $qrOptions = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => true,
            'eccLevel' => EccLevel::L,
            'scale' => 7,
            'quietzoneSize' => 4,
        ]);

        $qrSvgDataUri = (new QRCode($qrOptions))->render($payload);

        return view('wms.manifest', [
            'order' => $outboundOrder,
            'qrSvgDataUri' => $qrSvgDataUri,
            'skuSummary' => $skuSummary,
            'totals' => $totals,
        ]);
    }

    /**
     * Genera la Etiqueta Principal del Conjunto + 1 Etiqueta Individual por cada Caja
     * para envíos de proveedor (uno o varios artículos) o consulta directa de SKU.
     */
    public function productQrLabel(Request $request): View
    {
        $arrivalIdsParam = trim((string) $request->query('arrival_ids', ''));
        $arrivalId = $request->query('arrival_id');
        $entries = [];

        if ($arrivalIdsParam !== '') {
            $ids = array_values(array_filter(array_map('intval', explode(',', $arrivalIdsParam))));
            $arrivals = PlannedArrival::query()->with(['branch', 'product'])->whereIn('id', $ids)->orderBy('id')->get();
            foreach ($arrivals as $arr) {
                $entries[] = [
                    'arrival_id' => $arr->id,
                    'branch' => $arr->branch,
                    'product' => $arr->product,
                    'boxes' => max(1, $arr->pendingBoxes()),
                    'folio' => $arr->folio,
                    'dock' => $arr->dock_code,
                    'eta_window' => $arr->eta_window,
                    'supplier' => $arr->supplier_name,
                ];
            }
        } elseif ($arrivalId !== null) {
            $arr = PlannedArrival::query()->with(['branch', 'product'])->findOrFail((int) $arrivalId);
            $entries[] = [
                'arrival_id' => $arr->id,
                'branch' => $arr->branch,
                'product' => $arr->product,
                'boxes' => max(1, $arr->pendingBoxes()),
                'folio' => $arr->folio,
                'dock' => $arr->dock_code,
                'eta_window' => $arr->eta_window,
                'supplier' => $arr->supplier_name,
            ];
        }

        if ($entries === []) {
            $branch = Branch::query()->findOrFail((int) $request->query('branch_id', 1));
            $product = Product::query()->findOrFail((int) $request->query('product_id', 1));
            $entries[] = [
                'arrival_id' => null,
                'branch' => $branch,
                'product' => $product,
                'boxes' => max(1, min(100, (int) $request->query('boxes', 1))),
                'folio' => 'ETQ-'.now()->format('His'),
                'dock' => 'R2',
                'eta_window' => 'Programado hoy',
                'supplier' => 'Proveedor Papelero Autorizado CDMX',
            ];
        }

        $qrOptions = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => true,
            'eccLevel' => EccLevel::L,
            'scale' => 7,
            'quietzoneSize' => 4,
        ]);

        $totalBoxes = array_sum(array_column($entries, 'boxes'));
        $totalPieces = 0;
        $totalWeightKg = 0.0;
        $destinations = [];
        $summaryRows = [];

        foreach ($entries as $entry) {
            $pieces = $entry['boxes'] * (int) $entry['product']->master_factor;
            $weight = round($entry['boxes'] * (float) $entry['product']->master_weight_kg, 2);
            $totalPieces += $pieces;
            $totalWeightKg = round($totalWeightKg + $weight, 2);
            $destinations[$entry['branch']->code.' · '.$entry['branch']->name] = true;

            $summaryRows[] = [
                'sku' => $entry['product']->sku,
                'name' => $entry['product']->name,
                'branch_code' => $entry['branch']->code,
                'branch_name' => $entry['branch']->name,
                'rack_code' => $entry['branch']->rack_code,
                'stow_zone' => $entry['product']->stow_zone,
                'boxes' => $entry['boxes'],
                'pieces' => $pieces,
                'weight_kg' => $weight,
            ];
        }

        $showAllBoxes = $request->boolean('all_boxes', false);
        $boxLimit = $showAllBoxes ? max(1, $totalBoxes) : min($totalBoxes, 80);

        $labels = [];
        $globalIndex = 0;
        foreach ($entries as $entry) {
            $branch = $entry['branch'];
            $product = $entry['product'];
            $prefix = $entry['arrival_id'] !== null ? ('ARR'.$entry['arrival_id']) : $branch->code;

            for ($i = 1; $i <= $entry['boxes']; $i++) {
                $globalIndex++;
                if ($globalIndex > $boxLimit) {
                    break 2;
                }
                $bultoCode = sprintf('BLT-%s-%s-%02d', $prefix, $product->sku, $i);
                $payloadArray = [
                    'bulto' => $bultoCode,
                    'arrival_id' => $entry['arrival_id'],
                    'folio' => $entry['folio'],
                    'proveedor' => $entry['supplier'],
                    'anden_entrada' => $entry['dock'],
                    'destino' => $branch->code,
                    'sku' => $product->sku,
                    'cajas' => 1,
                    'piezas' => $product->master_factor,
                    'peso_kg' => $product->master_weight_kg,
                    'zona' => $product->stow_zone,
                ];
                $payloadJson = json_encode($payloadArray, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

                $labels[] = [
                    'index' => $globalIndex,
                    'total' => $totalBoxes,
                    'item_box_index' => $i,
                    'item_box_total' => $entry['boxes'],
                    'bulto_code' => $bultoCode,
                    'sku' => $product->sku,
                    'product_name' => $product->name,
                    'stow_zone' => $product->stow_zone,
                    'master_weight_kg' => $product->master_weight_kg,
                    'master_factor' => $product->master_factor,
                    'orientation_rule' => $product->orientation_rule,
                    'branch_code' => $branch->code,
                    'branch_name' => $branch->name,
                    'rack_code' => $branch->rack_code,
                    'dock' => $entry['dock'],
                    'supplier' => $entry['supplier'],
                    'payload_json' => $payloadJson,
                    'qr_data_uri' => (new QRCode($qrOptions))->render($payloadJson),
                ];
            }
        }

        $first = $entries[0];
        $batchPayload = json_encode([
            'bulto' => count($entries) > 1
                ? ('CONJUNTO-'.preg_replace('/[^A-Z0-9\-]/', '', strtoupper($first['folio'])))
                : sprintf('LOTE-%s-%s', $first['arrival_id'] !== null ? ('ARR'.$first['arrival_id']) : $first['branch']->code, $first['product']->sku),
            'arrival_id' => count($entries) === 1 ? $first['arrival_id'] : null,
            'folio' => $first['folio'],
            'proveedor' => $first['supplier'],
            'anden_entrada' => $first['dock'],
            'destino' => $first['branch']->code,
            'sku' => $first['product']->sku,
            'skus_totales' => count($entries),
            'cajas' => $totalBoxes,
            'piezas' => $totalPieces,
            'peso_kg' => $totalWeightKg,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $batchQrDataUri = (new QRCode($qrOptions))->render($batchPayload);

        return view('wms.qr-label', [
            'branch' => $first['branch'],
            'product' => $first['product'],
            'boxes' => $totalBoxes,
            'totalPieces' => $totalPieces,
            'totalWeightKg' => $totalWeightKg,
            'destinations' => array_keys($destinations),
            'summaryRows' => $summaryRows,
            'folio' => $first['folio'],
            'dock' => $first['dock'],
            'etaWindow' => $first['eta_window'],
            'supplier' => $first['supplier'],
            'batchPayload' => $batchPayload,
            'batchQrDataUri' => $batchQrDataUri,
            'labels' => $labels,
            'hasMoreBoxes' => $totalBoxes > count($labels),
        ]);
    }

    /**
     * Procesa el escaneo de la etiqueta integrada enviada por el proveedor, comprueba contra lo programado,
     * acomoda el artículo y genera automáticamente la SEGUNDA etiqueta láser continua del operador
     * indicando DÓNDE ACOMODAR, LUGAR DE ORIGEN y LUGAR DE SALIDA.
     */
    public function scanQr(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_payload' => ['required', 'string'],
            'distance_band' => ['nullable', 'in:under_10m,10_to_20m'],
            'auxiliary_equipment_confirmed' => ['nullable', 'boolean'],
        ]);

        $decoded = json_decode($validated['qr_payload'], true);
        if (! is_array($decoded)) {
            return response()->json([
                'message' => 'El código QR no contiene una etiqueta integrada válida del estándar CEDIS CDMX.',
            ], 422);
        }

        $bultoCode = trim((string) ($decoded['bulto'] ?? ''));
        $sku = trim((string) ($decoded['sku'] ?? ''));
        $branchCode = trim((string) ($decoded['destino'] ?? $decoded['sucursal'] ?? ''));
        $boxes = max(1, (int) ($decoded['cajas'] ?? 1));
        $folio = trim((string) ($decoded['folio'] ?? $decoded['asn'] ?? ''));
        $arrivalId = isset($decoded['arrival_id']) ? (int) $decoded['arrival_id'] : null;
        $originSupplier = trim((string) ($decoded['proveedor'] ?? ''));
        $originDock = trim((string) ($decoded['anden_entrada'] ?? ''));

        if ($bultoCode !== '' && PalletItem::query()->where('scanned_bulto_code', $bultoCode)->exists()) {
            return response()->json([
                'message' => "BULTO DUPLICADO: La etiqueta '{$bultoCode}' ya fue escaneada y comprobada previamente.",
            ], 422);
        }

        $product = Product::query()->where('sku', $sku)->first();
        if ($product === null) {
            return response()->json([
                'message' => "SKU '{$sku}' rechazado: no existe en el catálogo maestro auditado.",
            ], 422);
        }

        $branch = Branch::query()->where('code', $branchCode)->first();
        if ($branch === null) {
            return response()->json([
                'message' => "Destino '{$branchCode}' inválido: debe ser una de las 32 sucursales destino (SUC-01 a SUC-32).",
            ], 422);
        }

        $plannedArrival = null;
        if ($arrivalId !== null && $arrivalId > 0) {
            $plannedArrival = PlannedArrival::query()->find($arrivalId);
            if ($plannedArrival !== null && $plannedArrival->pendingBoxes() < $boxes) {
                return response()->json([
                    'message' => sprintf(
                        'EXCEDENTE DETECTADO: La llegada %s (%s) ya completó las %d cajas esperadas.',
                        $plannedArrival->folio,
                        $product->sku,
                        $plannedArrival->boxes_expected
                    ),
                ], 422);
            }
        } elseif ($folio !== '') {
            $plannedArrival = PlannedArrival::query()
                ->where('branch_id', $branch->id)
                ->where('product_id', $product->id)
                ->where('folio', $folio)
                ->whereIn('status', ['scheduled', 'partial'])
                ->first();
        } else {
            $plannedArrival = PlannedArrival::query()
                ->where('branch_id', $branch->id)
                ->where('product_id', $product->id)
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
                true,
                $bultoCode !== '' ? $bultoCode : null,
                $originSupplier !== '' ? $originSupplier : null,
                $originDock !== '' ? $originDock : null,
                null,
                (bool) ($validated['auxiliary_equipment_confirmed'] ?? true)
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $qrOptions = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => true,
            'eccLevel' => EccLevel::L,
            'scale' => 6,
            'quietzoneSize' => 4,
        ]);

        $laserLabels = [];
        $createdItemIds = [];

        foreach ($result['allocations'] as $alloc) {
            $createdItemIds[] = $alloc['pallet_item_id'];
            $internalPayload = json_encode([
                'etiqueta_laser' => $alloc['internal_label_code'],
                'sku' => $product->sku,
                'cajas' => $alloc['boxes_placed'],
                'acomodo_tarima' => $alloc['pallet_code'],
                'acomodo_zona' => $alloc['assigned_zone'],
                'acomodo_rack' => $alloc['planned_rack_coordinate'],
                'origen_proveedor' => $alloc['origin_supplier'],
                'origen_anden' => $alloc['origin_dock'],
                'origen_folio' => $alloc['origin_folio'],
                'salida_destino' => $branch->code.' '.$branch->name,
                'salida_anden' => $alloc['outbound_dock_target'],
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            $laserLabels[] = [
                'pallet_item_id' => $alloc['pallet_item_id'],
                'internal_label_code' => $alloc['internal_label_code'],
                'scanned_bulto' => $bultoCode ?: $alloc['origin_folio'],
                'sku' => $product->sku,
                'product_name' => $product->name,
                'boxes' => $alloc['boxes_placed'],
                'weight_kg' => $alloc['added_weight_kg'],
                'pallet_code' => $alloc['pallet_code'],
                'stow_zone' => $alloc['assigned_zone'],
                'zone_priority' => $alloc['zone_priority'],
                'layer_index' => $alloc['layer_index'],
                'rack_coordinate' => $alloc['planned_rack_coordinate'],
                'origin_supplier' => $alloc['origin_supplier'],
                'origin_dock' => $alloc['origin_dock'],
                'origin_folio' => $alloc['origin_folio'],
                'outbound_branch' => $branch->code.' · '.$branch->name,
                'outbound_dock_target' => $alloc['outbound_dock_target'],
                'outbound_cutoff' => $alloc['outbound_cutoff'],
                'verified_at' => now()->format('H:i:s'),
                'qr_data_uri' => (new QRCode($qrOptions))->render($internalPayload),
                'print_url' => route('wms.qr.laser-sheet', ['ids' => $alloc['pallet_item_id']], false),
            ];
        }

        $branches = Branch::query()->orderBy('number')->get();
        $freshArrival = $plannedArrival?->fresh();

        return response()->json([
            'scanned_bulto' => $bultoCode ?: ($folio ?: 'QR-VERIFICADO'),
            'arrival_updated' => $freshArrival !== null ? [
                'id' => $freshArrival->id,
                'boxes_expected' => $freshArrival->boxes_expected,
                'boxes_received' => $freshArrival->boxes_received,
                'pending_boxes' => $freshArrival->pendingBoxes(),
                'status' => $freshArrival->status,
            ] : null,
            'laser_labels' => $laserLabels,
            'continuous_print_url' => route('wms.qr.laser-sheet', ['ids' => implode(',', $createdItemIds)], false),
            'result' => $result,
            'state' => $this->buildWorkspacePayload($branch->fresh()),
            'branchesSummary' => $this->buildBranchesSummary($branches),
        ]);
    }

    /**
     * Renderiza la tira continua de etiquetas láser internas del operador:
     * 1 Etiqueta Principal del Conjunto Comprobado + 1 Etiqueta Individual por cada Caja (Acomodo + Origen + Salida).
     */
    public function internalLaserLabels(Request $request): View
    {
        $idsParam = trim((string) $request->query('ids', ''));
        $query = PalletItem::query()->with(['pallet.branch', 'product', 'placedBy'])->whereNotNull('internal_label_code');

        if ($idsParam !== '') {
            $ids = array_values(array_filter(array_map('intval', explode(',', $idsParam))));
            if ($ids !== []) {
                $query->whereIn('id', $ids);
            }
        } else {
            $query->latest('id')->limit(20);
        }

        $items = $query->orderBy('id')->get();
        $qrOptions = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => true,
            'eccLevel' => EccLevel::L,
            'scale' => 6,
            'quietzoneSize' => 4,
        ]);

        $totalBoxes = (int) $items->sum('quantity_boxes');
        $totalWeightKg = round((float) $items->sum('total_weight_kg'), 2);
        $showAllBoxes = $request->boolean('all_boxes', false);
        $boxLimit = $showAllBoxes ? max(1, $totalBoxes) : min($totalBoxes, 80);

        $summaryRows = [];
        $origins = [];
        $destinations = [];
        $rackCoords = [];
        $boxLabels = [];
        $globalBoxIndex = 0;

        foreach ($items as $item) {
            $branch = $item->pallet?->branch;
            $product = $item->product;
            $rackCoord = $item->planned_rack_coordinate
                ?: ($item->pallet?->rackCoordinate() ? ($branch?->rack_code.' · '.$item->pallet->rackCoordinate()) : ($branch?->rack_code.' · EN PISO'));
            $originSupplier = $item->origin_supplier ?? 'Proveedor Autorizado';
            $originDock = $item->origin_dock ?? 'R2';
            $outboundBranch = ($branch?->code ?? 'SUC-01').' · '.($branch?->name ?? 'CDMX');
            $outboundDock = $item->outbound_dock_target ?? 'ANDÉN E1 · OLA 1 TORTÓN';

            $origins[$originSupplier.' (Andén '.$originDock.')'] = true;
            $destinations[$outboundBranch.' → '.$outboundDock] = true;
            $rackCoords[$rackCoord] = true;

            $summaryRows[] = [
                'internal_label_code' => $item->internal_label_code,
                'sku' => $product?->sku ?? 'SKU',
                'product_name' => $product?->name ?? 'Producto',
                'boxes' => (int) $item->quantity_boxes,
                'weight_kg' => (float) $item->total_weight_kg,
                'rack_coordinate' => $rackCoord,
                'pallet_code' => $item->pallet?->code ?? 'TARIMA',
                'outbound_branch' => $outboundBranch,
            ];

            $qty = max(1, (int) $item->quantity_boxes);
            $unitWeight = $product !== null ? (float) $product->master_weight_kg : round((float) $item->total_weight_kg / $qty, 2);

            for ($b = 1; $b <= $qty; $b++) {
                $globalBoxIndex++;
                if ($globalBoxIndex > $boxLimit) {
                    continue;
                }
                $boxLabelCode = $qty > 1
                    ? sprintf('%s-C%02d', $item->internal_label_code, $b)
                    : (string) $item->internal_label_code;

                $payload = json_encode([
                    'etiqueta_laser' => $boxLabelCode,
                    'sku' => $product?->sku,
                    'caja' => $b.'/'.$qty,
                    'acomodo_tarima' => $item->pallet?->code,
                    'acomodo_rack' => $rackCoord,
                    'origen_proveedor' => $originSupplier,
                    'origen_anden' => $originDock,
                    'salida_destino' => $outboundBranch,
                    'salida_anden' => $outboundDock,
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

                $boxLabels[] = [
                    'index' => $globalBoxIndex,
                    'total' => $totalBoxes,
                    'box_in_item' => $b,
                    'item_total_boxes' => $qty,
                    'internal_label_code' => $boxLabelCode,
                    'base_label_code' => $item->internal_label_code,
                    'scanned_bulto_code' => $item->scanned_bulto_code ?: $item->batch_code,
                    'sku' => $product?->sku ?? 'SKU',
                    'product_name' => $product?->name ?? 'Producto',
                    'stow_zone' => $product?->stow_zone ?? 'Estándar',
                    'zone_priority' => $item->assigned_zone_priority,
                    'layer_index' => $item->layer_index,
                    'quantity_boxes' => 1,
                    'weight_kg' => $unitWeight,
                    'orientation' => $item->orientation_instruction,
                    'pallet_code' => $item->pallet?->code ?? 'TARIMA',
                    'rack_coordinate' => $rackCoord,
                    'origin_supplier' => $originSupplier,
                    'origin_dock' => $originDock,
                    'origin_folio' => $item->batch_code,
                    'outbound_branch' => $outboundBranch,
                    'outbound_dock_target' => $outboundDock,
                    'outbound_cutoff' => $item->outbound_cutoff ?? 'SALIDA PRÓXIMO CORTE (≥90%)',
                    'operator_name' => $item->placedBy?->name ?? 'Operador Almacén',
                    'created_at' => $item->created_at?->format('d/m/Y H:i:s') ?? now()->format('d/m/Y H:i:s'),
                    'qr_data_uri' => (new QRCode($qrOptions))->render($payload),
                ];
            }
        }

        $masterLabel = null;
        if ($items->isNotEmpty()) {
            $masterCode = sprintf('LSR-CONJUNTO-%s', now()->format('His'));
            $masterPayload = json_encode([
                'tipo' => 'CONJUNTO_LASER_OPERADOR',
                'codigo' => $masterCode,
                'partidas' => $items->count(),
                'cajas_totales' => $totalBoxes,
                'peso_total_kg' => $totalWeightKg,
                'racks' => array_keys($rackCoords),
                'salidas' => array_keys($destinations),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            $masterLabel = [
                'code' => $masterCode,
                'items_count' => $items->count(),
                'total_boxes' => $totalBoxes,
                'total_weight_kg' => $totalWeightKg,
                'origins' => array_keys($origins),
                'destinations' => array_keys($destinations),
                'rack_coords' => array_keys($rackCoords),
                'summary_rows' => $summaryRows,
                'qr_data_uri' => (new QRCode($qrOptions))->render($masterPayload),
            ];
        }

        return view('wms.internal-laser-labels', [
            'masterLabel' => $masterLabel,
            'labels' => collect($boxLabels),
            'totalBoxes' => $totalBoxes,
            'hasMoreBoxes' => $totalBoxes > count($boxLabels),
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
            'asn_folio' => ['required', 'string', 'max:40'],
            'remission_folio' => ['required', 'string', 'min:4', 'max:40'],
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
        $folioBase = strtoupper(trim($validated['asn_folio'])).' · '.strtoupper(trim($validated['remission_folio']));

        $qrOptions = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => true,
            'eccLevel' => EccLevel::L,
            'scale' => 6,
            'quietzoneSize' => 4,
        ]);

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

            $sampleBoxCode = sprintf('BLT-ARR%d-%s-01', $arrival->id, $arrival->product->sku);
            $samplePayload = json_encode([
                'bulto' => $sampleBoxCode,
                'arrival_id' => $arrival->id,
                'folio' => $arrival->folio,
                'proveedor' => $arrival->supplier_name,
                'anden_entrada' => $arrival->dock_code,
                'destino' => $arrival->branch->code,
                'sku' => $arrival->product->sku,
                'cajas' => 1,
                'piezas' => $arrival->product->master_factor,
                'peso_kg' => $arrival->product->master_weight_kg,
                'zona' => $arrival->product->stow_zone,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

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
                'sample_bulto_code' => $sampleBoxCode,
                'sample_qr_payload' => $samplePayload,
                'sample_qr_svg' => (new QRCode($qrOptions))->render($samplePayload),
                'qr_label_url' => route('wms.qr.label', ['arrival_id' => $arrival->id], false),
                'acomodo_url' => route('wms.index', ['branch' => $arrival->branch->code], false),
            ];
        }

        $createdIds = array_column($created, 'id');

        return response()->json([
            'message' => sprintf('Entrega registrada con éxito (%d partidas enviadas al CEDIS CDMX y etiquetas QR generadas: 1 principal del conjunto + 1 por cada caja).', count($created)),
            'batch_qr_label_url' => route('wms.qr.label', ['arrival_ids' => implode(',', $createdIds)], false),
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
        $buildingCounts = Pallet::query()
            ->where('status', 'building')
            ->selectRaw('branch_id, COUNT(*) as total')
            ->groupBy('branch_id')
            ->pluck('total', 'branch_id');

        $pendingCounts = PlannedArrival::query()
            ->whereIn('status', ['scheduled', 'partial'])
            ->selectRaw('branch_id, COUNT(*) as total')
            ->groupBy('branch_id')
            ->pluck('total', 'branch_id');

        $summary = [];

        foreach ($branches as $branch) {
            $plan = $this->outboundService->evaluateBranchDispatchPlan($branch);
            $buildingCount = (int) ($buildingCounts[$branch->id] ?? 0);
            $pendingCount = (int) ($pendingCounts[$branch->id] ?? 0);

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
