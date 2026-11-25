<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\EmergencyDispatchRequest;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\Product;
use App\Services\OutboundOrderService;
use App\Services\SlottingEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class EmergencyDispatchController extends Controller
{
    public function __construct(
        private readonly OutboundOrderService $outboundService,
        private readonly SlottingEngineService $slottingEngine,
    ) {}

    /**
     * Lista en vivo las solicitudes de salida de emergencia (<90%) para Embarques y Gerencia.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'requests' => self::loadFormattedRequests(),
        ]);
    }

    /**
     * Crea una solicitud urgente desde Embarques para forzar la salida de un Tortón incompleto (<90%).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_code' => ['required', 'string', 'exists:branches,code'],
            'dock_code' => ['required', 'in:E1,E2,E3'],
            'unit_plate' => ['required', 'string', 'max:24'],
            'driver_name' => ['required', 'string', 'max:120'],
            'emergency_reason' => ['required', 'string', 'min:5', 'max:255'],
            'estimated_pallets' => ['nullable', 'integer', 'min:1', 'max:12'],
            'estimated_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'weight_pct' => ['nullable', 'numeric', 'min:0'],
            'estimated_volume_m3' => ['nullable', 'numeric', 'min:0'],
            'volume_pct' => ['nullable', 'numeric', 'min:0'],
        ]);

        $branch = Branch::query()->where('code', $validated['branch_code'])->firstOrFail();
        $plan = $this->outboundService->evaluateBranchDispatchPlan($branch);

        $pallets = $plan['selected_pallet_count'] > 0
            ? (int) $plan['selected_pallet_count']
            : max(1, min(11, (int) ($validated['estimated_pallets'] ?? 6)));
        $weightKg = $plan['total_weight_kg'] > 0
            ? (float) $plan['total_weight_kg']
            : round((float) ($validated['estimated_weight_kg'] ?? 6500.0), 2);
        $weightPct = $plan['weight_pct'] > 0
            ? (float) $plan['weight_pct']
            : round((float) ($validated['weight_pct'] ?? round(($weightKg / 12000.0) * 100, 1)), 1);
        $volumeM3 = $plan['total_volume_m3'] > 0
            ? (float) $plan['total_volume_m3']
            : round((float) ($validated['estimated_volume_m3'] ?? 15.8), 2);
        $volumePct = $plan['volume_pct'] > 0
            ? (float) $plan['volume_pct']
            : round((float) ($validated['volume_pct'] ?? round(($volumeM3 / 28.59) * 100, 1)), 1);

        $nextSeq = ((int) EmergencyDispatchRequest::query()->max('id')) + 1;
        $folio = sprintf('EMG-2026-%03d', $nextSeq);
        while (EmergencyDispatchRequest::query()->where('folio', $folio)->exists()) {
            $nextSeq++;
            $folio = sprintf('EMG-2026-%03d', $nextSeq);
        }

        $emergency = EmergencyDispatchRequest::create([
            'folio' => $folio,
            'branch_id' => $branch->id,
            'dock_code' => $validated['dock_code'],
            'unit_plate' => strtoupper(trim($validated['unit_plate'])),
            'driver_name' => trim($validated['driver_name']),
            'estimated_pallets' => $pallets,
            'estimated_weight_kg' => $weightKg,
            'weight_pct' => $weightPct,
            'estimated_volume_m3' => $volumeM3,
            'volume_pct' => $volumePct,
            'emergency_reason' => trim($validated['emergency_reason']),
            'status' => 'pending',
            'requested_by_user_id' => Auth::id(),
        ]);

        return response()->json([
            'message' => sprintf('Alerta urgente %s enviada a Gerencia General para autorizar salida de %s.', $emergency->folio, $branch->code),
            'request' => self::formatRequest($emergency->load(['branch', 'requestedBy', 'reviewedBy', 'outboundOrder'])),
            'requests' => self::loadFormattedRequests(),
        ], 201);
    }

    /**
     * El Gerente acepta la salida de emergencia (<90%), disparando la Orden de Salida real y su PDF/QR.
     */
    public function approve(Request $request, EmergencyDispatchRequest $emergencyDispatchRequest): JsonResponse
    {
        if ($emergencyDispatchRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Esta solicitud de emergencia ya fue procesada previamente.',
            ], 422);
        }

        $validated = $request->validate([
            'manager_notes' => ['nullable', 'string', 'max:255'],
        ]);

        $branch = $emergencyDispatchRequest->branch;
        $this->ensureSlottedPalletsExistForEmergency($branch, (int) $emergencyDispatchRequest->estimated_pallets);

        $managerName = Auth::user()?->name ?? 'Gerente General';
        $auditReason = sprintf(
            'EMERGENCIA %s AUTORIZADA POR %s: %s',
            $emergencyDispatchRequest->folio,
            $managerName,
            $emergencyDispatchRequest->emergency_reason
        );

        try {
            $order = $this->outboundService->dispatchOutboundOrder(
                $branch->fresh(),
                $emergencyDispatchRequest->dock_code,
                $emergencyDispatchRequest->unit_plate,
                $emergencyDispatchRequest->driver_name,
                true,
                $auditReason,
                Auth::id()
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $emergencyDispatchRequest->update([
            'status' => 'approved',
            'reviewed_by_user_id' => Auth::id(),
            'manager_notes' => trim((string) ($validated['manager_notes'] ?? '')) ?: 'Salida de emergencia autorizada por Gerencia General.',
            'outbound_order_id' => $order->id,
            'reviewed_at' => now(),
        ]);

        $fresh = $emergencyDispatchRequest->fresh(['branch', 'requestedBy', 'reviewedBy', 'outboundOrder']);

        return response()->json([
            'message' => sprintf(
                'Salida de emergencia %s aprobada. Se generó la Orden de Salida %s y su PDF/QR para Embarques.',
                $fresh->folio,
                $order->folio
            ),
            'request' => self::formatRequest($fresh),
            'requests' => self::loadFormattedRequests(),
        ]);
    }

    /**
     * El Gerente rechaza la salida de emergencia para mantener la consolidación ≥90%.
     */
    public function reject(Request $request, EmergencyDispatchRequest $emergencyDispatchRequest): JsonResponse
    {
        if ($emergencyDispatchRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Esta solicitud de emergencia ya fue procesada previamente.',
            ], 422);
        }

        $validated = $request->validate([
            'manager_notes' => ['nullable', 'string', 'max:255'],
        ]);

        $emergencyDispatchRequest->update([
            'status' => 'rejected',
            'reviewed_by_user_id' => Auth::id(),
            'manager_notes' => trim((string) ($validated['manager_notes'] ?? '')) ?: 'Solicitud rechazada: esperar consolidación normativa ≥90%.',
            'reviewed_at' => now(),
        ]);

        $fresh = $emergencyDispatchRequest->fresh(['branch', 'requestedBy', 'reviewedBy', 'outboundOrder']);

        return response()->json([
            'message' => sprintf('Solicitud %s rechazada por Gerencia General.', $fresh->folio),
            'request' => self::formatRequest($fresh),
            'requests' => self::loadFormattedRequests(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function loadFormattedRequests(): array
    {
        return EmergencyDispatchRequest::query()
            ->with(['branch', 'requestedBy', 'reviewedBy', 'outboundOrder'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest('id')
            ->limit(25)
            ->get()
            ->map(static fn (EmergencyDispatchRequest $r): array => self::formatRequest($r))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function formatRequest(EmergencyDispatchRequest $r): array
    {
        return [
            'id' => $r->id,
            'folio' => $r->folio,
            'branch_code' => $r->branch?->code ?? 'SUC-01',
            'branch_name' => $r->branch?->name ?? 'CDMX',
            'rack_code' => $r->branch?->rack_code ?? 'RACK-01',
            'dock_code' => $r->dock_code,
            'unit_plate' => $r->unit_plate,
            'driver_name' => $r->driver_name,
            'estimated_pallets' => $r->estimated_pallets,
            'estimated_weight_kg' => $r->estimated_weight_kg,
            'weight_pct' => $r->weight_pct,
            'estimated_volume_m3' => $r->estimated_volume_m3,
            'volume_pct' => $r->volume_pct,
            'emergency_reason' => $r->emergency_reason,
            'status' => $r->status,
            'requested_by' => $r->requestedBy?->name ?? 'Jefe de Embarque',
            'reviewed_by' => $r->reviewedBy?->name,
            'manager_notes' => $r->manager_notes,
            'outbound_folio' => $r->outboundOrder?->folio,
            'manifest_url' => $r->outboundOrder !== null
                ? route('wms.outbound.manifest', $r->outboundOrder, false)
                : null,
            'created_at' => $r->created_at?->format('H:i · d/m/Y') ?? '',
            'reviewed_at' => $r->reviewed_at?->format('H:i · d/m/Y'),
        ];
    }

    /**
     * Si una sucursal del tablero aún no tenía tarimas físicas sembradas en `pallets` (solo datos proyectados),
     * materializa sus tarimas en tránsito en el rack para emitir una Orden de Salida y PDF reales.
     */
    private function ensureSlottedPalletsExistForEmergency(Branch $branch, int $targetPallets): void
    {
        $hasSlotted = $branch->pallets()->where('status', 'slotted')->exists();
        if ($hasSlotted) {
            return;
        }

        // Si había tarimas en armado (building), subirlas primero al rack
        $buildingPallets = $branch->pallets()->where('status', 'building')->get();
        foreach ($buildingPallets as $bp) {
            $this->slottingEngine->slotPalletIntoRack($bp);
        }

        if ($branch->pallets()->where('status', 'slotted')->exists()) {
            return;
        }

        $product = Product::query()->where('sku', 'PAP-007')->first() ?? Product::query()->first();
        if ($product === null) {
            return;
        }

        $count = max(1, min(8, $targetPallets));
        for ($i = 1; $i <= $count; $i++) {
            $boxes = 20;
            $goodsWeight = round($boxes * (float) $product->master_weight_kg, 2);
            $vol = round($boxes * (float) $product->master_volume_m3, 4);

            $pallet = Pallet::create([
                'code' => sprintf('TAR-%02d-E%02d', $branch->number, $i),
                'branch_id' => $branch->id,
                'status' => 'building',
                'tare_weight_kg' => Pallet::WOOD_TARE_KG,
                'goods_weight_kg' => $goodsWeight,
                'total_weight_kg' => round(Pallet::WOOD_TARE_KG + $goodsWeight, 2),
                'volume_m3' => $vol,
                'estimated_height_m' => $this->slottingEngine->estimatePalletHeightM($vol),
                'box_count' => $boxes,
                'highest_zone_priority' => $product->zone_priority,
                'has_liquids' => (bool) $product->is_liquid,
                'has_hygroscopic' => (bool) $product->is_hygroscopic,
                'pallet_profile' => 'mixta_estratificada',
                'wave_number' => 1,
            ]);

            PalletItem::create([
                'pallet_id' => $pallet->id,
                'product_id' => $product->id,
                'batch_code' => 'TRANSITO-CEDIS',
                'quantity_boxes' => $boxes,
                'assigned_zone_priority' => $product->zone_priority,
                'layer_index' => 2,
                'total_weight_kg' => $goodsWeight,
                'total_volume_m3' => $vol,
                'orientation_instruction' => $product->orientation_rule,
                'placement_reason' => 'Carga consolidada en tránsito liberada por emergencia.',
                'origin_supplier' => 'Proveedor Consolidado CEDIS CDMX',
                'origin_dock' => 'R2',
            ]);

            $this->slottingEngine->slotPalletIntoRack($pallet->fresh());
        }
    }
}
