<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deal extends Model
{
    use HasFactory;

    protected $fillable = [
        'market_id',
        'mode',
        'status',
        'entry_average_price',
        'entry_amount',
        'exit_average_price',
        'exit_amount',
        'realized_pnl',
        'realized_pnl_percent',
        'metadata',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['opening', 'entered', 'exiting', 'stop_loss']);
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(TradingOrder::class);
    }

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class);
    }

    public function remainingAmount(): float
    {
        return max(0.0, (float) $this->entry_amount - (float) $this->exit_amount);
    }
}
