<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trade extends Model
{
    use HasFactory;

    protected $fillable = [
        'market_id',
        'deal_id',
        'order_id',
        'exchange_trade_id',
        'mode',
        'side',
        'price',
        'amount',
        'quote_amount',
        'fee',
        'fee_asset',
        'filled_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'filled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(TradingOrder::class, 'order_id');
    }
}
