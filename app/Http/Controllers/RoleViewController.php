<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\PlannedArrival;
use App\Models\Product;
use App\Support\CedisDemoData;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RoleViewController extends Controller
{
    public function gerente(): View
    {
        $branches = CedisDemoData::branches();

        return view('roles.gerente', [
            'user' => Auth::user(),
            'activeRole' => 'gerente',
            'branches' => $branches,
            'docks' => CedisDemoData::docks(),
            'purchaseOrders' => CedisDemoData::purchaseOrders(),
            'incidents' => CedisDemoData::incidents(),
            'quotation' => CedisDemoData::quotation(),
            'scheduledArrivals' => $this->loadScheduledArrivals(),
        ]);
    }

    public function embarques(): View
    {
        $branches = CedisDemoData::branches();
        // Priorizar primero por Fecha Compromiso de Entrega (sla_priority ASC) y luego por % de peso (DESC)
        usort($branches, static fn (array $a, array $b): int => ($a['sla_priority'] <=> $b['sla_priority']) ?: ($b['weight_pct'] <=> $a['weight_pct']));

        return view('roles.embarques', [
            'user' => Auth::user(),
            'activeRole' => 'jefe_embarque',
            'branches' => $branches,
            'docks' => CedisDemoData::docks(),
            'scheduledArrivals' => $this->loadScheduledArrivals(),
        ]);
    }

    public function recepcion(): View
    {
        return view('roles.recepcion', [
            'user' => Auth::user(),
            'activeRole' => 'operador',
            'incidents' => CedisDemoData::incidents(),
        ]);
    }

    public function proveedor(): View
    {
        return view('roles.proveedor', [
            'user' => Auth::user(),
            'activeRole' => 'proveedor',
            'purchaseOrders' => CedisDemoData::purchaseOrders(),
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
            'activeRole' => 'sucursal',
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
