<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutboundOrder extends Model
{
    public const int TORTON_MAX_PALLETS = 12;

    public const float TORTON_MAX_WEIGHT_KG = 12000.0;

    public const float TORTON_MIN_WEIGHT_KG = 10800.0;

    public const float TORTON_MAX_VOLUME_M3 = 28.59;

    public const float TORTON_MIN_VOLUME_M3 = 25.73;

    public const float TORTON_THRESHOLD_PCT = 90.0;

    /** @var list<string> */
    protected $fillable = [
        'folio',
        'branch_id',
        'status',
        'dock_code',
        'unit_plate',
        'driver_name',
        'pallet_count',
        'total_weight_kg',
        'weight_pct',
        'total_volume_m3',
        'volume_pct',
        'exception_authorized',
        'exception_reason',
        'loading_sequence',
        'created_by_user_id',
        'dispatched_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pallet_count' => 'integer',
            'total_weight_kg' => 'float',
            'weight_pct' => 'float',
            'total_volume_m3' => 'float',
            'volume_pct' => 'float',
            'exception_authorized' => 'boolean',
            'loading_sequence' => 'array',
            'dispatched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<Pallet, $this>
     */
    public function pallets(): HasMany
    {
        return $this->hasMany(Pallet::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
