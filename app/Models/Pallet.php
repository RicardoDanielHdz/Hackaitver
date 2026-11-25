<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pallet extends Model
{
    public const float MAX_TOTAL_WEIGHT_KG = 1000.0;

    public const float WOOD_TARE_KG = 22.0;

    public const float MAX_USABLE_VOLUME_M3 = 1.95;

    public const float GROSS_PALLET_VOLUME_M3 = 2.19;

    public const float MAX_HEIGHT_M = 1.85;

    public const float WOOD_HEIGHT_M = 0.15;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'branch_id',
        'status',
        'rack_level',
        'rack_position',
        'tare_weight_kg',
        'goods_weight_kg',
        'total_weight_kg',
        'volume_m3',
        'estimated_height_m',
        'box_count',
        'highest_zone_priority',
        'has_liquids',
        'has_hygroscopic',
        'pallet_profile',
        'wave_number',
        'outbound_order_id',
        'truck_slot',
        'slotted_at',
        'dispatched_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rack_level' => 'integer',
            'rack_position' => 'integer',
            'tare_weight_kg' => 'float',
            'goods_weight_kg' => 'float',
            'total_weight_kg' => 'float',
            'volume_m3' => 'float',
            'estimated_height_m' => 'float',
            'box_count' => 'integer',
            'highest_zone_priority' => 'integer',
            'has_liquids' => 'boolean',
            'has_hygroscopic' => 'boolean',
            'wave_number' => 'integer',
            'truck_slot' => 'integer',
            'slotted_at' => 'datetime',
            'dispatched_at' => 'datetime',
        ];
    }

    public function rackCoordinate(): ?string
    {
        if ($this->rack_level === null || $this->rack_position === null) {
            return null;
        }

        return sprintf('N%d·P%d', $this->rack_level, $this->rack_position);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<OutboundOrder, $this>
     */
    public function outboundOrder(): BelongsTo
    {
        return $this->belongsTo(OutboundOrder::class);
    }

    /**
     * @return HasMany<PalletItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PalletItem::class);
    }
}
