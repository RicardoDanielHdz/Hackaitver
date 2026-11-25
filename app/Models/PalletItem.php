<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PalletItem extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'pallet_id',
        'product_id',
        'batch_code',
        'quantity_boxes',
        'assigned_zone_priority',
        'layer_index',
        'total_weight_kg',
        'total_volume_m3',
        'orientation_instruction',
        'placement_reason',
        'ergonomic_warning',
        'placed_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_boxes' => 'integer',
            'assigned_zone_priority' => 'integer',
            'layer_index' => 'integer',
            'total_weight_kg' => 'float',
            'total_volume_m3' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Pallet, $this>
     */
    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function placedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by_user_id');
    }
}
