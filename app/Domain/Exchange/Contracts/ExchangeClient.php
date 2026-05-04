<?php

namespace App\Domain\Exchange\Contracts;

use App\Domain\Exchange\DTO\BalanceData;
use App\Domain\Exchange\DTO\FairPriceData;
use App\Domain\Exchange\DTO\OrderBook;
use App\Domain\Exchange\DTO\OrderStatusData;
use App\Domain\Exchange\DTO\PlacedOrderData;
use App\Domain\Exchange\DTO\PlaceOrderData;

interface ExchangeClient
{
    public function name(): string;

    public function getFairPrice(string $symbol): FairPriceData;

    public function getOrderBook(string $symbol): OrderBook;

    public function placeOrder(PlaceOrderData $order): PlacedOrderData;

    public function cancelOrder(string $clientId): void;

    public function getOrderStatus(string $clientId): OrderStatusData;

    public function getBalance(string $asset): BalanceData;
}
