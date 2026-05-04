<?php

namespace App\Infrastructure\Exchange\Paper;

use App\Domain\Exchange\Contracts\ExchangeClient;
use App\Domain\Exchange\DTO\BalanceData;
use App\Domain\Exchange\DTO\FairPriceData;
use App\Domain\Exchange\DTO\OrderBook;
use App\Domain\Exchange\DTO\OrderStatusData;
use App\Domain\Exchange\DTO\PlacedOrderData;
use App\Domain\Exchange\DTO\PlaceOrderData;
use App\Models\TradingOrder;

class PaperExchangeClient implements ExchangeClient
{
    public function __construct(private readonly ExchangeClient $marketDataClient) {}

    public function name(): string
    {
        return 'paper';
    }

    public function getFairPrice(string $symbol): FairPriceData
    {
        return $this->marketDataClient->getFairPrice($symbol);
    }

    public function getOrderBook(string $symbol): OrderBook
    {
        return $this->marketDataClient->getOrderBook($symbol);
    }

    public function placeOrder(PlaceOrderData $order): PlacedOrderData
    {
        return new PlacedOrderData(
            clientId: $order->clientId,
            externalId: 'paper-'.$order->clientId,
            status: 'open',
            raw: ['paper' => true],
        );
    }

    public function cancelOrder(string $clientId): void
    {
        TradingOrder::query()->where('client_id', $clientId)->update(['status' => 'cancelled']);
    }

    public function getOrderStatus(string $clientId): OrderStatusData
    {
        $order = TradingOrder::query()->where('client_id', $clientId)->firstOrFail();
        $book = $this->getOrderBook($order->symbol);
        $topAsk = $book->topAsk();
        $topBid = $book->topBid();
        $filled = false;

        if ($order->side === 'buy' && $topAsk && (float) $order->price >= (float) $topAsk->price) {
            $filled = true;
        }

        if ($order->side === 'sell' && $topBid && (float) $order->price <= (float) $topBid->price) {
            $filled = true;
        }

        return new OrderStatusData(
            clientId: $clientId,
            status: $filled ? 'filled' : (string) $order->status,
            filledAmount: $filled ? (string) $order->amount : (string) $order->filled_amount,
            averagePrice: $filled ? (string) $order->price : null,
            raw: ['paper' => true],
        );
    }

    public function getBalance(string $asset): BalanceData
    {
        return new BalanceData(
            asset: $asset,
            available: (string) config('trading.paper.default_quote_balance'),
            locked: '0',
            raw: ['paper' => true],
        );
    }
}
