<?php

namespace App\Filament\Widgets;

use App\Domain\Trading\Services\PnlResetService;
use App\Models\Deal;
use App\Models\TradingOrder;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;

class PnlOverviewWidget extends BaseWidget implements HasActions
{
    use InteractsWithActions;

    protected ?string $heading = 'PnL overview';

    public function getSectionContentComponent(): Component
    {
        return Section::make()
            ->heading($this->getHeading())
            ->description($this->getDescription())
            ->headerActions([
                Action::make('resetPnl')
                    ->label('Reset PnL')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Reset TMN PnL?')
                    ->modalDescription('Realized PnL (TMN) and Unrealized PnL (TMN) will be set to zero from this point forward. Open positions and deal history are unchanged.')
                    ->modalSubmitActionLabel('Yes, reset')
                    ->action(function (PnlResetService $service): void {
                        $service->resetTmn();
                        $this->cachedStats = null;

                        Notification::make()
                            ->title('PnL reset')
                            ->body('Realized and unrealized PnL (TMN) now start from zero.')
                            ->success()
                            ->send();
                    }),
            ])
            ->schema($this->getCachedStats())
            ->columns($this->getColumns())
            ->contained(false)
            ->gridContainer();
    }

    protected function getStats(): array
    {
        $realizedPnlByQuote = $this->realizedPnlByQuoteAsset();
        $pnlResetService = app(PnlResetService::class);

        return [
            $this->realizedPnlStat('TMN', $realizedPnlByQuote, $pnlResetService->adjustedRealizedTmn()),
            $this->realizedPnlStat('USDT', $realizedPnlByQuote),
            $this->unrealizedPnlTmnStat($pnlResetService->adjustedUnrealizedTmn()),
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
    protected function realizedPnlStat(string $quoteAsset, Collection $totals, ?float $adjustedPnl = null): Stat
    {
        $pnl = $adjustedPnl ?? (float) ($totals->get($quoteAsset) ?? 0);

        return Stat::make("Realized PnL ({$quoteAsset})", number_format($pnl, 2).' '.$quoteAsset)
            ->description('Sum of closed deal PnL in '.$quoteAsset)
            ->color($pnl >= 0 ? 'success' : 'danger');
    }

    protected function unrealizedPnlTmnStat(float $pnl): Stat
    {
        return Stat::make('Unrealized PnL (TMN)', number_format($pnl, 2).' TMN')
            ->description('Sum of unexited amount × last price per market')
            ->color('info');
    }
}
