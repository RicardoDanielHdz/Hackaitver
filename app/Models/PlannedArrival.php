<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlannedArrival extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'folio',
        'branch_id',
        'product_id',
        'boxes_expected',
        'boxes_received',
        'dock_code',
        'eta_window',
        'supplier_name',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'boxes_expected' => 'integer',
            'boxes_received' => 'integer',
        ];
    }

    public function pendingBoxes(): int
    {
        return max(0, $this->boxes_expected - $this->boxes_received);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
