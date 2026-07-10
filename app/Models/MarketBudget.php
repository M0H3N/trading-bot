<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketBudget extends Model
{
    protected $fillable = [
        'market_id',
        'deal_type',
        'budget_asset',
        'budget',
        'used_budget',
    ];

    public function scopeLong(Builder $query): Builder
    {
        return $query->where('deal_type', Deal::DIRECTION_LONG);
    }

    public function scopeShort(Builder $query): Builder
    {
        return $query->where('deal_type', Deal::DIRECTION_SHORT);
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function availableBudget(): float
    {
        return max(0.0, (float) $this->budget - (float) $this->used_budget);
    }
}
