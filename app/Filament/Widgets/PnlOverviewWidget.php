<?php

namespace App\Filament\Widgets;

use App\Domain\Trading\Services\UnexitedPositionService;
use App\Models\Deal;
use App\Models\TradingOrder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;

class PnlOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $realizedPnlByQuote = $this->realizedPnlByQuoteAsset();

        return [
            $this->realizedPnlStat('TMN', $realizedPnlByQuote),
            $this->realizedPnlStat('USDT', $realizedPnlByQuote),
            $this->unrealizedPnlTmnStat(),
            Stat::make('Open Deals', (string) Deal::query()->open()->count()),
            Stat::make('Active Orders', (string) TradingOrder::query()->active()->count()),
        ];
    }

    /**
     * @return Collection<string, float>
     */
    protected function realizedPnlByQuoteAsset(): Collection
    {
        return Deal::query()
            ->join('markets', 'markets.id', '=', 'deals.market_id')
            ->selectRaw('markets.quote_asset as quote_asset, SUM(deals.realized_pnl) as total_pnl')
            ->groupBy('markets.quote_asset')
            ->pluck('total_pnl', 'quote_asset')
            ->map(fn (mixed $total): float => (float) $total);
    }

    /**
     * @param  Collection<string, float>  $totals
     */
    protected function realizedPnlStat(string $quoteAsset, Collection $totals): Stat
    {
        $pnl = (float) ($totals->get($quoteAsset) ?? 0);

        return Stat::make("Realized PnL ({$quoteAsset})", number_format($pnl, 2).' '.$quoteAsset)
            ->description('Sum of closed deal PnL in '.$quoteAsset)
            ->color($pnl >= 0 ? 'success' : 'danger');
    }

    protected function unrealizedPnlTmnStat(): Stat
    {
        $value = app(UnexitedPositionService::class)->totalUnrealizedValueTmn();

        return Stat::make('Unrealized PnL (TMN)', number_format($value, 2).' TMN')
            ->description('Sum of unexited amount × last price per market')
            ->color('info');
    }
}
