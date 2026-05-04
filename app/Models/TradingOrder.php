<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradingOrder extends Model
{
    use HasFactory;

    protected $table = 'orders';

    protected $fillable = [
        'market_id',
        'deal_id',
        'exchange',
        'symbol',
        'client_id',
        'external_id',
        'mode',
        'side',
        'type',
        'status',
        'price',
        'amount',
        'filled_amount',
        'quote_amount',
        'tick_offset',
        'last_checked_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_checked_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'open', 'partially_filled']);
    }

    public function scopeEntry(Builder $query): Builder
    {
        return $query->where('side', 'buy');
    }

    public function scopeExit(Builder $query): Builder
    {
        return $query->where('side', 'sell');
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class, 'order_id');
    }
}
