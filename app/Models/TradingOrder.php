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

    /**
     * Orders that still need status polling, or entry-leg orders marked filled before a trade was recorded.
     */
    public function scopeMonitorable(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->active()
                ->orWhere(function (Builder $query): void {
                    $query->entryLeg()
                        ->where('status', 'filled')
                        ->whereDoesntHave(
                            'trades',
                            fn (Builder $trades): Builder => $trades->whereColumn('trades.side', 'orders.side'),
                        );
                })
                ->orWhere(function (Builder $query): void {
                    $query->entryLeg()
                        ->where('status', 'filled')
                        ->whereHas(
                            'deal',
                            fn (Builder $deal): Builder => $deal->where('status', 'opening'),
                        );
                })
                ->orWhere(function (Builder $query): void {
                    $query->entryLeg()
                        ->where('status', 'cancelled')
                        ->where('filled_amount', '>', 0)
                        ->whereHas(
                            'deal',
                            fn (Builder $deal): Builder => $deal->where('status', 'opening'),
                        );
                });
        });
    }

    /**
     * First leg of a deal: buy for long, sell for short.
     */
    public function scopeEntryLeg(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where(function (Builder $long): void {
                $long->where('side', 'buy')
                    ->whereHas('deal', fn (Builder $deal): Builder => $deal->where('direction', Deal::DIRECTION_LONG));
            })->orWhere(function (Builder $short): void {
                $short->where('side', 'sell')
                    ->whereHas('deal', fn (Builder $deal): Builder => $deal->where('direction', Deal::DIRECTION_SHORT));
            });
        });
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
