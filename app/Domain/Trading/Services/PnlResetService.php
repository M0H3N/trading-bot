<?php

namespace App\Domain\Trading\Services;

use App\Models\Deal;
use App\Models\TradingSetting;

class PnlResetService
{
    public const REALIZED_TMN_BASELINE_KEY = 'pnl_realized_tmn_baseline';

    public const UNREALIZED_TMN_BASELINE_KEY = 'pnl_unrealized_tmn_baseline';

    public const UNEXITED_BASELINES_KEY = 'pnl_unexited_baselines';

    public function __construct(
        protected UnexitedPositionService $unexitedPositionService,
    ) {}

    public function rawRealizedTmn(): float
    {
        return (float) Deal::query()
            ->join('markets', 'markets.id', '=', 'deals.market_id')
            ->where('markets.quote_asset', 'TMN')
            ->sum('deals.realized_pnl');
    }

    public function rawUnrealizedTmn(): float
    {
        return $this->unexitedPositionService->totalUnrealizedValueTmn();
    }

    public function realizedTmnBaseline(): float
    {
        return (float) TradingSetting::value(self::REALIZED_TMN_BASELINE_KEY, 0);
    }

    public function unrealizedTmnBaseline(): float
    {
        return (float) TradingSetting::value(self::UNREALIZED_TMN_BASELINE_KEY, 0);
    }

    public function adjustedRealizedTmn(): float
    {
        return $this->rawRealizedTmn() - $this->realizedTmnBaseline();
    }

    public function adjustedUnrealizedTmn(): float
    {
        return $this->rawUnrealizedTmn() - $this->unrealizedTmnBaseline();
    }

    public function resetTmn(): void
    {
        $this->storeBaseline(self::REALIZED_TMN_BASELINE_KEY, $this->rawRealizedTmn());
        $this->storeBaseline(self::UNREALIZED_TMN_BASELINE_KEY, $this->rawUnrealizedTmn());
        $this->storeUnexitedBaselines();
    }

    /**
     * @return array<string, array{amount: float, value_tmn: float}>
     */
    public function unexitedBaselines(): array
    {
        $raw = TradingSetting::value(self::UNEXITED_BASELINES_KEY, '{}');

        if (! is_string($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $baselines = [];

        foreach ($decoded as $baseAsset => $baseline) {
            if (! is_array($baseline)) {
                continue;
            }

            $baselines[(string) $baseAsset] = [
                'amount' => (float) ($baseline['amount'] ?? 0),
                'value_tmn' => (float) ($baseline['value_tmn'] ?? 0),
            ];
        }

        return $baselines;
    }

    protected function storeUnexitedBaselines(): void
    {
        $baselines = [];

        foreach ($this->unexitedPositionService->aggregatedByBaseAssetQuery()->get() as $row) {
            $baselines[(string) $row->base_asset] = [
                'amount' => (float) $row->total_unexited_amount,
                'value_tmn' => (float) $row->unrealized_value_tmn,
            ];
        }

        TradingSetting::query()->updateOrCreate(
            ['key' => self::UNEXITED_BASELINES_KEY],
            [
                'value' => json_encode($baselines),
                'type' => 'string',
                'label' => 'PnL unexited baselines',
                'is_public' => false,
            ],
        );
    }

    protected function storeBaseline(string $key, float $value): void
    {
        TradingSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => number_format($value, 12, '.', ''),
                'type' => 'decimal',
                'label' => str($key)->headline()->toString(),
                'is_public' => false,
            ],
        );
    }
}
