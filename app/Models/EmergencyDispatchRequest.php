<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyDispatchRequest extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'folio',
        'branch_id',
        'dock_code',
        'unit_plate',
        'driver_name',
        'estimated_pallets',
        'estimated_weight_kg',
        'weight_pct',
        'estimated_volume_m3',
        'volume_pct',
        'emergency_reason',
        'status',
        'requested_by_user_id',
        'reviewed_by_user_id',
        'manager_notes',
        'outbound_order_id',
        'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estimated_pallets' => 'integer',
            'estimated_weight_kg' => 'float',
            'weight_pct' => 'float',
            'estimated_volume_m3' => 'float',
            'volume_pct' => 'float',
            'reviewed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * @return BelongsTo<OutboundOrder, $this>
     */
    public function outboundOrder(): BelongsTo
    {
        return $this->belongsTo(OutboundOrder::class);
    }
}
