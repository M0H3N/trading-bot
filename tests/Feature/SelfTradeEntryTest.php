<?php

namespace Tests\Feature;

use App\Domain\Trading\Services\TradeRecorder;
use App\Models\Deal;
use App\Models\Market;
use App\Models\Trade;
use App\Models\TradingOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelfTradeEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_buy_entry_excludes_quantity_when_matching_sell_already_recorded(): void
    {
        $market = $this->pepeMarket();

        $seller = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'live',
            'status' => 'closed',
            'entry_average_price' => '0.3937',
            'entry_amount' => '5109867',
            'exit_average_price' => '0.3937',
            'exit_amount' => '5109611',
            'opened_at' => now()->subMinute(),
            'closed_at' => now(),
        ]);

        $buyer = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'live',
            'status' => 'opening',
            'opened_at' => now(),
        ]);

        $sellOrder = TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $seller->id,
            'exchange' => 'wallex',
            'symbol' => 'PEPETMN',
            'client_id' => 'Deal-430-tb-wallex-pepetmn-sell-test',
            'mode' => 'live',
            'side' => 'sell',
            'type' => 'limit',
            'status' => 'filled',
            'price' => '0.3937',
            'amount' => '5109611',
            'filled_amount' => '5109611',
            'quote_amount' => '2011653.8507',
        ]);

        Trade::query()->create([
            'market_id' => $market->id,
            'deal_id' => $seller->id,
            'order_id' => $sellOrder->id,
            'mode' => 'live',
            'side' => 'sell',
            'price' => '0.3937',
            'amount' => '5109611',
            'quote_amount' => '2011653.8507',
            'fee' => '100.582692535',
            'fee_asset' => 'TMN',
            'filled_at' => now()->subSeconds(10),
        ]);

        $buyOrder = TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $buyer->id,
            'exchange' => 'wallex',
            'symbol' => 'PEPETMN',
            'client_id' => 'tb-wallex-pepetmn-buy-test',
            'mode' => 'live',
            'side' => 'buy',
            'type' => 'limit',
            'status' => 'filled',
            'price' => '0.3937',
            'amount' => '6208220',
            'filled_amount' => '6208220',
            'quote_amount' => '2444175.3140',
        ]);

        app(TradeRecorder::class)->recordFilledOrder(
            $buyOrder,
            '0.3937',
            '6208220',
            ['result' => ['executedQty' => '6208220', 'fee' => '310.411', 'feeAsset' => 'PEPE']],
        );

        $buyer->refresh();

        $this->assertEquals(1098609, (float) $buyer->entry_amount);
        $this->assertDatabaseHas('trades', [
            'deal_id' => $buyer->id,
            'side' => 'buy',
            'amount' => '1098609.000000000000',
        ]);
    }

    public function test_prior_buy_entry_is_reduced_when_matching_sell_is_recorded_later(): void
    {
        $market = $this->pepeMarket();

        $seller = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'live',
            'status' => 'exiting',
            'entry_average_price' => '0.3937',
            'entry_amount' => '5109867',
            'opened_at' => now()->subMinute(),
        ]);

        $buyer = Deal::query()->create([
            'market_id' => $market->id,
            'mode' => 'live',
            'status' => 'entered',
            'entry_average_price' => '0.3937',
            'entry_amount' => '6208220',
            'opened_at' => now()->subSeconds(20),
        ]);

        $buyOrder = TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $buyer->id,
            'exchange' => 'wallex',
            'symbol' => 'PEPETMN',
            'client_id' => 'tb-wallex-pepetmn-buy-test',
            'mode' => 'live',
            'side' => 'buy',
            'type' => 'limit',
            'status' => 'filled',
            'price' => '0.3937',
            'amount' => '6208220',
            'filled_amount' => '6208220',
            'quote_amount' => '2444175.3140',
        ]);

        Trade::query()->create([
            'market_id' => $market->id,
            'deal_id' => $buyer->id,
            'order_id' => $buyOrder->id,
            'mode' => 'live',
            'side' => 'buy',
            'price' => '0.3937',
            'amount' => '6208220',
            'quote_amount' => '2444175.3140',
            'fee' => '310.411',
            'fee_asset' => 'PEPE',
            'filled_at' => now()->subSeconds(15),
        ]);

        $sellOrder = TradingOrder::query()->create([
            'market_id' => $market->id,
            'deal_id' => $seller->id,
            'exchange' => 'wallex',
            'symbol' => 'PEPETMN',
            'client_id' => 'Deal-430-tb-wallex-pepetmn-sell-test',
            'mode' => 'live',
            'side' => 'sell',
            'type' => 'limit',
            'status' => 'filled',
            'price' => '0.3937',
            'amount' => '5109611',
            'filled_amount' => '5109611',
            'quote_amount' => '2011653.8507',
        ]);

        app(TradeRecorder::class)->recordFilledOrder(
            $sellOrder,
            '0.3937',
            '5109611',
            ['result' => ['executedQty' => '5109611', 'fee' => '100.582692535', 'feeAsset' => 'TMN']],
        );

        $buyer->refresh();

        $this->assertEquals(1098609, (float) $buyer->entry_amount);
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
}
