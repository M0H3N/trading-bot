<?php

namespace Tests\Unit;

use App\Domain\Trading\Services\ExitManagementService;
use App\Models\Deal;
use App\Models\Market;
use App\Models\Trade;
use App\Models\TradingOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class ExitOrderAmountTest extends TestCase
{
    use RefreshDatabase;

    public function test_remaining_amount_ignores_buy_fee_below_market_step(): void
    {
        $market = $this->btcMarket(stepSize: '6');
        $deal = $this->enteredDeal($market, entryAmount: '0.000121');
        $order = $this->buyOrder($market, $deal);

        Trade::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'order_id' => $order->id,
            'mode' => 'live',
            'side' => 'buy',
            'price' => '10900000000',
            'amount' => '0.000121',
            'quote_amount' => '1318900',
            'fee' => '0.00000000605',
            'fee_asset' => 'BTC',
            'filled_at' => now(),
        ]);

        $this->assertSame(0.000121, $deal->fresh()->remainingAmount());
    }

    public function test_remaining_amount_subtracts_buy_fee_at_or_above_market_step(): void
    {
        $market = $this->pepeMarket();
        $deal = $this->enteredDeal($market, entryAmount: '2826466');
        $order = $this->buyOrder($market, $deal);

        Trade::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'order_id' => $order->id,
            'mode' => 'live',
            'side' => 'buy',
            'price' => '0.4675',
            'amount' => '2826466',
            'quote_amount' => '1321372.855',
            'fee' => '141.3233',
            'fee_asset' => 'PEPE',
            'filled_at' => now(),
        ]);

        $this->assertEqualsWithDelta(2826324.6767, $deal->fresh()->remainingAmount(), 0.0001);
    }

    public function test_floor_amount_truncates_to_step_without_float_drift(): void
    {
        $this->assertSame('0.000121', $this->floorAmount(0.000121, 6));
        $this->assertSame('0.000120', $this->floorAmount(0.00012099999395, 6));
        $this->assertSame('2826324', $this->floorAmount(2826324.6767, 0));
    }

    public function test_remaining_amount_ignores_sell_fee_in_quote_asset_for_short_deal(): void
    {
        $market = $this->trxMarket();
        $deal = $this->enteredShortDeal($market, entryAmount: '5.2');
        $order = $this->sellOrder($market, $deal);

        Trade::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'order_id' => $order->id,
            'mode' => 'live',
            'side' => 'sell',
            'price' => '59679',
            'amount' => '5.2',
            'quote_amount' => '310330.8',
            'fee' => '15.516540',
            'fee_asset' => 'TMN',
            'filled_at' => now(),
        ]);

        $this->assertSame(5.2, $deal->fresh()->remainingAmount());
    }

    public function test_exit_amount_for_btc_uses_full_entry_when_buy_fee_is_below_step(): void
    {
        $market = $this->btcMarket(stepSize: '6');
        $deal = $this->enteredDeal($market, entryAmount: '0.000121');
        $order = $this->buyOrder($market, $deal);

        Trade::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'order_id' => $order->id,
            'mode' => 'live',
            'side' => 'buy',
            'price' => '10900000000',
            'amount' => '0.000121',
            'quote_amount' => '1318900',
            'fee' => '0.00000000605',
            'fee_asset' => 'BTC',
            'filled_at' => now(),
        ]);

        $remaining = $deal->fresh()->remainingAmount();

        $this->assertSame('0.000121', $this->floorAmount($remaining, 6));
    }

    private function floorAmount(float $amount, int $stepSize): string
    {
        $method = new ReflectionMethod(ExitManagementService::class, 'floorAmount');

        return $method->invoke(app(ExitManagementService::class), $amount, $stepSize);
    }

    private function btcMarket(string $stepSize = '6'): Market
    {
        return Market::query()->create([
            'exchange' => 'wallex',
            'symbol' => 'BTCTMN',
            'base_asset' => 'BTC',
            'quote_asset' => 'TMN',
            'tick_size' => '1',
            'step_size' => $stepSize,
            'is_active' => true,
        ]);
    }

    private function pepeMarket(): Market
    {
        return Market::query()->create([
            'exchange' => 'wallex',
            'symbol' => 'PEPETMN',
            'base_asset' => 'PEPE',
            'quote_asset' => 'TMN',
            'tick_size' => '4',
            'step_size' => '0',
            'is_active' => true,
        ]);
    }

    private function enteredDeal(Market $market, string $entryAmount): Deal
    {
        return Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'live',
            'status' => 'entered',
            'entry_average_price' => '1',
            'entry_amount' => $entryAmount,
            'exit_average_price' => '0',
            'exit_amount' => '0',
            'opened_at' => now(),
        ]);
    }

    private function buyOrder(Market $market, Deal $deal): TradingOrder
    {
        return TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => $market->symbol,
            'client_id' => 'test-buy-'.$deal->id,
            'mode' => 'live',
            'side' => 'buy',
            'type' => 'limit',
            'status' => 'filled',
            'price' => '1',
            'amount' => $deal->entry_amount,
            'quote_amount' => '1',
        ]);
    }

    private function trxMarket(): Market
    {
        return Market::query()->create([
            'exchange' => 'wallex',
            'symbol' => 'TRXTMN',
            'base_asset' => 'TRX',
            'quote_asset' => 'TMN',
            'tick_size' => '1',
            'step_size' => '1',
            'is_active' => true,
        ]);
    }

    private function enteredShortDeal(Market $market, string $entryAmount): Deal
    {
        return Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'live',
            'direction' => 'short',
            'status' => 'entered',
            'entry_average_price' => '59679',
            'entry_amount' => $entryAmount,
            'exit_average_price' => '0',
            'exit_amount' => '0',
            'opened_at' => now(),
        ]);
    }

    private function sellOrder(Market $market, Deal $deal): TradingOrder
    {
        return TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $deal->id,
            'exchange' => 'wallex',
            'symbol' => $market->symbol,
            'client_id' => 'test-sell-'.$deal->id,
            'mode' => 'live',
            'side' => 'sell',
            'type' => 'limit',
            'status' => 'filled',
            'price' => '59679',
            'amount' => $deal->entry_amount,
            'quote_amount' => '310330.8',
        ]);
    }
}
