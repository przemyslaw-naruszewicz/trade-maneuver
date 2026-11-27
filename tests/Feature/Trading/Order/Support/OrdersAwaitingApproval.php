<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Order\Support;

use DateTimeImmutable;
use Illuminate\Support\Str;
use Tests\Unit\Trading\InvestmentConfiguration\Support\InvestmentConfigurationBuilder;
use Tests\Unit\Trading\Order\Support\OrderBuilder;
use Tests\Unit\Trading\TradeSignal\Support\TradeSignalBuilder;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository\InvestmentConfigurationRepositoryInterface;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationRef;
use TradeManeuver\Trading\Domain\Order\Order;
use TradeManeuver\Trading\Domain\Order\Repository\OrderRepositoryInterface;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;
use TradeManeuver\Trading\Domain\TradeSignal\Enum\Direction;
use TradeManeuver\Trading\Domain\TradeSignal\Repository\TradeSignalRepositoryInterface;
use TradeManeuver\Trading\Domain\TradeSignal\TradeSignal;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Confidence;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Horizon;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Instrument;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Mic;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ReferencePrice;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalContent;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\UtcTimestamp;

/**
 * Stores orders awaiting approval through the repositories, each with its own long trade signal on a fictional
 * instrument at the exchange DEMO, under the investment configuration of the builder.
 */
trait OrdersAwaitingApproval
{
    private function addTheInvestmentConfiguration(): void
    {
        $this->app->make(InvestmentConfigurationRepositoryInterface::class)->add(InvestmentConfigurationBuilder::anInvestmentConfiguration()->create());
    }

    /**
     * Adds the order of a new trade signal with the reference price, under the settings 1000.00 EUR and 2 % of the
     * order builder.
     */
    private function addOrder(string $symbol, string $validUntil, string $referencePrice = '10.00'): void
    {
        $tradeSignals = $this->app->make(TradeSignalRepositoryInterface::class);
        $tradeSignal = TradeSignal::receive(
            new TradeSignalId(Str::uuid7()->toString()),
            new AnalyticsApplicationId('demo-analytics'),
            new TradeSignalContent(
                UtcTimestamp::fromString('2026-11-07T09:00:00Z'),
                new InvestmentConfigurationRef('demo-investment'),
                new Instrument($symbol, new Mic('DEMO')),
                Direction::Long,
                Confidence::of('0.85'),
                Horizon::of(5, null),
                null,
                ReferencePrice::of($referencePrice, 'EUR', '2026-11-07T08:59:00Z'),
                UtcTimestamp::fromString($validUntil),
            ),
            new DateTimeImmutable(TradeSignalBuilder::RECEIVED_AT),
        );
        $tradeSignals->add($tradeSignal);
        $order = $tradeSignal->createOrder(
            new OrderId(Str::uuid7()->toString()),
            new InvestmentConfigurationId(InvestmentConfigurationBuilder::ID),
            OrderBuilder::settings(),
            $tradeSignals->nextOutcomeIdentity(),
            new DateTimeImmutable(OrderBuilder::CREATED_AT),
        );
        $this->assertInstanceOf(Order::class, $order);
        $this->app->make(OrderRepositoryInterface::class)->add($order);
    }
}
