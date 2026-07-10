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

    protected $attributes = [
        'direction' => 'long',
    ];

    public const CLOSED_STATUSES = ['closed', 'stop_loss_closed', 'manually_closed', 'insufficient_balance'];

    public const DIRECTION_LONG = 'long';

    public const DIRECTION_SHORT = 'short';

    protected $fillable = [
        'market_id',
        'mode',
        'direction',
        'status',
        'entry_average_price',
        'entry_amount',
        'entry_quote',
        'exit_average_price',
        'exit_amount',
        'exit_quote',
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

    public function scopeLong(Builder $query): Builder
    {
        return $query->where('direction', self::DIRECTION_LONG);
    }

    public function scopeShort(Builder $query): Builder
    {
        return $query->where('direction', self::DIRECTION_SHORT);
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

    public function isLong(): bool
    {
        return $this->direction === self::DIRECTION_LONG;
    }

    public function isShort(): bool
    {
        return $this->direction === self::DIRECTION_SHORT;
    }

    public function entrySide(): string
    {
        return $this->isShort() ? 'sell' : 'buy';
    }

    public function exitSide(): string
    {
        return $this->isShort() ? 'buy' : 'sell';
    }

    public function hasActiveEntryOrder(): bool
    {
        return $this->orders()
            ->where('side', $this->entrySide())
            ->active()
            ->exists();
    }

    public function isFullyEntered(): bool
    {
        if ((float) $this->entry_amount <= 0) {
            return false;
        }

        return in_array($this->status, ['entered', 'exiting', 'stop_loss'], true);
    }

    public function remainingAmount(): float
    {
        $market = $this->market()->first(['step_size', 'base_asset']);
        $precision = (int) $market->step_size;
        $scale = max($precision + 4, 12);
        $baseAsset = strtoupper($market->base_asset);

        $entryAmount = bcadd(number_format((float) $this->entry_amount, $scale, '.', ''), '0', $scale);
        $exitAmount = bcadd(number_format((float) $this->exit_amount, $scale, '.', ''), '0', $scale);
        $entryFees = '0';

        foreach ($this->trades()->where('side', $this->entrySide())->get(['fee', 'fee_asset']) as $trade) {
            if (strtoupper((string) $trade->fee_asset) !== $baseAsset) {
                continue;
            }

            $entryFees = bcadd($entryFees, number_format((float) $trade->fee, $scale, '.', ''), $scale);
        }

        $oneStep = $precision > 0
            ? bcdiv('1', bcpow('10', (string) $precision, 0), $scale)
            : '1';

        if (bccomp($entryFees, $oneStep, $scale) < 0) {
            $entryFees = '0';
        }

        $remaining = bcsub(bcsub($entryAmount, $entryFees, $scale), $exitAmount, $scale);

        return max(0.0, (float) $remaining);
    }
}
