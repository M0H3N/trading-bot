<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Market extends Model
{
    use HasFactory;

    protected $attributes = [
        'long_enabled' => true,
        'short_enabled' => false,
    ];

    protected $fillable = [
        'exchange',
        'symbol',
        'base_asset',
        'quote_asset',
        'tick_size',
        'step_size',
        'last_price',
        'min_order_amount',
        'is_active',
        'long_enabled',
        'short_enabled',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'long_enabled' => 'boolean',
            'short_enabled' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(TradingOrder::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function minPriceIncrement(): float
    {
        $tickSize = (int) $this->tick_size;

        return $tickSize === 0 ? 1.0 : 10 ** -$tickSize;
    }
}
