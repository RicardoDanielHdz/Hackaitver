<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'number',
        'code',
        'name',
        'zone',
        'distance_km',
        'rack_code',
        'max_rack_pallets',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'distance_km' => 'integer',
            'max_rack_pallets' => 'integer',
        ];
    }

    /**
     * @return HasMany<Pallet, $this>
     */
    public function pallets(): HasMany
    {
        return $this->hasMany(Pallet::class);
    }

    /**
     * @return HasMany<PlannedArrival, $this>
     */
    public function plannedArrivals(): HasMany
    {
        return $this->hasMany(PlannedArrival::class);
    }

    /**
     * @return HasMany<OutboundOrder, $this>
     */
    public function outboundOrders(): HasMany
    {
        return $this->hasMany(OutboundOrder::class);
    }
}
