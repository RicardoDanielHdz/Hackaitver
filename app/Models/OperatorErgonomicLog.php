<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatorErgonomicLog extends Model
{
    public const float LIMIT_UNDER_10M_KG = 10000.0;

    public const float LIMIT_10_TO_20M_KG = 6000.0;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'operator_name',
        'shift_date',
        'distance_band',
        'moved_weight_kg',
        'boxes_moved',
        'assisted_moves',
        'last_alert',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'moved_weight_kg' => 'float',
            'boxes_moved' => 'integer',
            'assisted_moves' => 'integer',
        ];
    }

    public function dailyLimitKg(): float
    {
        return $this->distance_band === '10_to_20m'
            ? self::LIMIT_10_TO_20M_KG
            : self::LIMIT_UNDER_10M_KG;
    }

    public function usagePct(): float
    {
        return round(($this->moved_weight_kg / $this->dailyLimitKg()) * 100.0, 1);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
