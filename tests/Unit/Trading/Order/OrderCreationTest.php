<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\Order;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Trading\InvestmentConfiguration\Support\InvestmentConfigurationBuilder;
use Tests\Unit\Trading\Order\Support\OrderBuilder;
use Tests\Unit\Trading\TradeSignal\Support\TradeSignalBuilder;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationId;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;
use TradeManeuver\Trading\Domain\TradeSignal\Enum\DeclineReason;
use TradeManeuver\Trading\Domain\TradeSignal\Enum\SignalOutcomeType;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalDeclined;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcomeId;

final class OrderCreationTest extends TestCase
{
    public function test_an_order_amount_that_cannot_buy_one_unit_declines_the_trade_signal_with_order_amount_too_small(): void
    {
        // An order amount of 1000.00 EUR buys no unit at the limit price 1000.01 EUR.
        $tradeSignal = TradeSignalBuilder::received(referencePrice: '1000.01');
        $tradeSignal->releaseEvents();

        $order = $tradeSignal->createOrder(
            new OrderId(OrderBuilder::ID),
            new InvestmentConfigurationId(InvestmentConfigurationBuilder::ID),
            OrderBuilder::settings('1000.00', '0'),
            new SignalOutcomeId(TradeSignalBuilder::OUTCOME_ID),
            new DateTimeImmutable(OrderBuilder::CREATED_AT),
        );

        $this->assertNull($order);
        $this->assertFalse($tradeSignal->awaitsEvaluation());
        $outcome = $tradeSignal->outcome();
        $this->assertNotNull($outcome);
        $this->assertSame(SignalOutcomeType::SignalDeclined, $outcome->type);
        $this->assertSame(DeclineReason::OrderAmountTooSmall, $outcome->reason);
        $this->assertSame(TradeSignalBuilder::OUTCOME_ID, $outcome->id->toString());
        $this->assertSame(OrderBuilder::CREATED_AT, $outcome->occurredAt->format('Y-m-d\TH:i:s.u\Z'));
        $events = $tradeSignal->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TradeSignalDeclined::class, $events[0]);
        $this->assertSame(DeclineReason::OrderAmountTooSmall, $events[0]->reason);
        $this->assertSame($outcome, $events[0]->outcome);
    }
}
