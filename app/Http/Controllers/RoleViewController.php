<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\PalletItem;
use App\Models\PlannedArrival;
use App\Models\Product;
use App\Models\User;
use App\Support\CedisDemoData;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RoleViewController extends Controller
{
    public function gerente(): View
    {
        $branches = CedisDemoData::branches();

        $usersList = User::query()
            ->orderBy('role')
            ->orderBy('name')
            ->get()
            ->map(static fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'classification' => in_array($u->role, ['proveedor', 'sucursal'], true) ? 'EXTERNO' : 'INTERNO CEDIS',
                'job_title' => $u->job_title,
                'assigned_branch_code' => $u->assigned_branch_code,
                'supplier_rfc' => $u->supplier_rfc,
                'is_active' => (bool) $u->is_active,
                'created_at' => $u->created_at?->format('d/m/Y H:i') ?? '',
            ])
            ->values();

        return view('roles.gerente', [
            'user' => Auth::user(),
            'branches' => $branches,
            'docks' => CedisDemoData::docks(),
            'incidents' => CedisDemoData::incidents(),
            'scheduledArrivals' => $this->loadScheduledArrivals(),
            'usersList' => $usersList,
        ]);
    }

    public function embarques(): View
    {
        $branches = CedisDemoData::branches();

        // Enriquecer cada destino con el desglose real (o representativo) del contenido embarcado para la etiqueta final Tortón
        $dbBranches = Branch::query()
            ->with(['pallets' => fn ($q) => $q->where('status', 'slotted')->with(['items.product'])])
            ->get()
            ->keyBy('code');

        foreach ($branches as &$branch) {
            $dbBranch = $dbBranches->get($branch['code']);
            $itemsMap = [];
            $totalBoxes = 0;
            $totalPieces = 0;
            $hasLiquids = false;
            $hasHygroscopic = false;

            if ($dbBranch !== null && $dbBranch->pallets->isNotEmpty()) {
                foreach ($dbBranch->pallets->take(12) as $pallet) {
                    foreach ($pallet->items as $item) {
                        $prod = $item->product;
                        if ($prod === null) {
                            continue;
                        }
                        $sku = $prod->sku;
                        $boxes = (int) $item->quantity_boxes;
                        $pieces = $boxes * (int) $prod->master_factor;
                        $totalBoxes += $boxes;
                        $totalPieces += $pieces;
                        $hasLiquids = $hasLiquids || (bool) $prod->is_liquid;
                        $hasHygroscopic = $hasHygroscopic || (bool) $prod->is_hygroscopic;

                        if (! isset($itemsMap[$sku])) {
                            $itemsMap[$sku] = [
                                'sku' => $sku,
                                'name' => $prod->name,
                                'category' => $prod->category,
                                'stow_zone' => $prod->stow_zone,
                                'boxes' => 0,
                                'pieces' => 0,
                                'weight_kg' => 0.0,
                                'is_liquid' => (bool) $prod->is_liquid,
                                'supplier' => $item->origin_supplier ?: 'Distribuidora Papelera CDMX',
                            ];
                        }
                        $itemsMap[$sku]['boxes'] += $boxes;
                        $itemsMap[$sku]['pieces'] += $pieces;
                        $itemsMap[$sku]['weight_kg'] = round($itemsMap[$sku]['weight_kg'] + (float) $item->total_weight_kg, 1);
                    }
                }
            }

            if ($itemsMap === []) {
                $palletsCount = max(1, min(12, (int) $branch['pallets']));
                $itemsMap = [
                    [
                        'sku' => 'PAP-007',
                        'name' => 'Papel bond blanco carta 75g resma 500 hojas',
                        'category' => 'Papel y cuadernos',
                        'stow_zone' => 'Base / Pesado',
                        'boxes' => $palletsCount * 20,
                        'pieces' => $palletsCount * 200,
                        'weight_kg' => round($branch['weight_kg'] * 0.52, 1),
                        'is_liquid' => false,
                        'supplier' => 'Copamex Papelero S.A.',
                    ],
                    [
                        'sku' => 'OFI-006',
                        'name' => 'Grapas estándar 26/6 caja 5,000 pzas',
                        'category' => 'Suministros de oficina',
                        'stow_zone' => 'Base / Pesado',
                        'boxes' => $palletsCount * 12,
                        'pieces' => $palletsCount * 240,
                        'weight_kg' => round($branch['weight_kg'] * 0.23, 1),
                        'is_liquid' => false,
                        'supplier' => 'Distribuidora Metropolitana S.A.',
                    ],
                    [
                        'sku' => 'ESC-010',
                        'name' => 'Bolígrafo punto mediano tinta azul caja 12 pzas',
                        'category' => 'Escritura',
                        'stow_zone' => 'Intermedio / Estándar',
                        'boxes' => $palletsCount * 10,
                        'pieces' => $palletsCount * 480,
                        'weight_kg' => round($branch['weight_kg'] * 0.15, 1),
                        'is_liquid' => false,
                        'supplier' => 'Azor México S.A.',
                    ],
                    [
                        'sku' => 'ARC-011',
                        'name' => 'Carpeta panorámica 3 argollas 2 pulgadas carta',
                        'category' => 'Archivo y organización',
                        'stow_zone' => 'Superior / Ligero',
                        'boxes' => $palletsCount * 8,
                        'pieces' => $palletsCount * 96,
                        'weight_kg' => round($branch['weight_kg'] * 0.10, 1),
                        'is_liquid' => false,
                        'supplier' => 'Oxford Archivo S.A.',
                    ],
                ];
                $totalBoxes = array_sum(array_column($itemsMap, 'boxes'));
                $totalPieces = array_sum(array_column($itemsMap, 'pieces'));
                $hasHygroscopic = true;
            }

            $branch['cargo_contents'] = array_values($itemsMap);
            $branch['total_boxes'] = $totalBoxes;
            $branch['total_pieces'] = $totalPieces;
            $branch['has_liquids'] = $hasLiquids;
            $branch['has_hygroscopic'] = $hasHygroscopic;
        }
        unset($branch);

        // Priorizar primero por Fecha Compromiso de Entrega (sla_priority ASC) y luego por % de peso (DESC)
        usort($branches, static fn (array $a, array $b): int => ($a['sla_priority'] <=> $b['sla_priority']) ?: ($b['weight_pct'] <=> $a['weight_pct']));

        return view('roles.embarques', [
            'user' => Auth::user(),
            'branches' => $branches,
            'docks' => CedisDemoData::docks(),
            'scheduledArrivals' => $this->loadScheduledArrivals(),
        ]);
    }

    public function recepcion(): View
    {
        $scheduledArrivals = PlannedArrival::query()
            ->with(['branch', 'product'])
            ->whereIn('status', ['scheduled', 'partial'])
            ->orderBy('dock_code')
            ->orderBy('id')
            ->get();

        $recentLaserItems = PalletItem::query()
            ->with(['pallet.branch', 'product'])
            ->whereNotNull('internal_label_code')
            ->latest('id')
            ->limit(15)
            ->get();

        return view('roles.recepcion', [
            'user' => Auth::user(),
            'incidents' => CedisDemoData::incidents(),
            'scheduledArrivals' => $scheduledArrivals,
            'recentLaserItems' => $recentLaserItems,
        ]);
    }

    public function proveedor(): View
    {
        return view('roles.proveedor', [
            'user' => Auth::user(),
            'docks' => CedisDemoData::docks(),
            'products' => Product::query()->orderBy('catalog_order')->get(),
            'branches' => Branch::query()->orderBy('number')->get(),
            'scheduledArrivals' => $this->loadScheduledArrivals(),
        ]);
    }

    public function sucursal(): View
    {
        return view('roles.sucursal', [
            'user' => Auth::user(),
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, PlannedArrival>
     */
    private function loadScheduledArrivals(): \Illuminate\Database\Eloquent\Collection
    {
        return PlannedArrival::query()
            ->with(['branch', 'product'])
            ->whereIn('status', ['scheduled', 'partial'])
            ->latest('id')
            ->limit(25)
            ->get();
    }
}
