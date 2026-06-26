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

    public const CLOSED_STATUSES = ['closed', 'manually_closed', 'insufficient_balance'];

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
        'exited',
        'unexited_amount',
        'metadata',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'exited' => 'boolean',
            'metadata' => 'array',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['opening', 'entered', 'exiting', 'stop_loss']);
    }

    public function scopeClose(Builder $query): Builder
    {
        return $query->whereIn('status', self::CLOSED_STATUSES);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
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

    public function hasActiveEntryOrder(): bool
    {
        return $this->orders()->entry()->active()->exists();
    }

    public function remainingAmount(): float
    {
        $buyFees = (float) $this->trades()
            ->where('side', 'buy')
            ->sum('fee');

        return max(0.0, (float) $this->entry_amount - (float) $buyFees -  (float) $this->exit_amount);
    }
}
