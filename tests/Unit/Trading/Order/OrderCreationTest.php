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
use TradeManeuver\Trading\Domain\TradeSignal\Exception\InvalidTradeSignalStateException;
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

    public function test_a_trade_signal_that_passed_evaluation_leads_to_an_order_priced_and_sized_with_a_snapshot_of_the_settings(): void
    {
        $order = OrderBuilder::awaitingApproval();

        $this->assertSame(OrderBuilder::ID, $order->id()->toString());
        $state = $order->state();
        $this->assertSame(TradeSignalBuilder::ID, $state->tradeSignalId->toString());
        $this->assertSame(InvestmentConfigurationBuilder::ID, $state->investmentConfigurationId->toString());
        $this->assertSame('DEMO_A', $state->instrument->symbol());
        $this->assertSame('DEMO', $state->instrument->mic()->code());
        $this->assertSame('10.20', $state->limitPrice->amount());
        $this->assertSame('EUR', $state->limitPrice->currencyCode());
        $this->assertSame(98, $state->quantity->units());
        $this->assertEquals(OrderBuilder::settings(), $state->settings);
        $this->assertSame(OrderBuilder::CREATED_AT, $state->createdAt->format('Y-m-d\TH:i:s.u\Z'));

        $events = $order->releaseEvents();
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame(OrderBuilder::ID, $event->id->toString());
        $this->assertSame($state->tradeSignalId, $event->tradeSignalId);
        $this->assertSame($state->investmentConfigurationId, $event->investmentConfigurationId);
        $this->assertSame($state->instrument, $event->instrument);
        $this->assertSame($state->limitPrice, $event->limitPrice);
        $this->assertSame($state->quantity, $event->quantity);
        $this->assertSame($state->settings, $event->settings);
        $this->assertSame($state->createdAt, $event->createdAt);
        $this->assertSame([], $order->releaseEvents());
    }

    public function test_creating_the_order_leaves_the_trade_signal_without_a_signal_outcome(): void
    {
        $tradeSignal = TradeSignalBuilder::received(referencePrice: '1000.00');
        $tradeSignal->releaseEvents();

        $order = $tradeSignal->createOrder(
            new OrderId(OrderBuilder::ID),
            new InvestmentConfigurationId(InvestmentConfigurationBuilder::ID),
            OrderBuilder::settings('1000.00', '0'),
            new SignalOutcomeId(TradeSignalBuilder::OUTCOME_ID),
            new DateTimeImmutable(OrderBuilder::CREATED_AT),
        );

        $this->assertNotNull($order);
        $this->assertSame(1, $order->state()->quantity->units());
        $this->assertTrue($tradeSignal->awaitsEvaluation());
        $this->assertSame([], $tradeSignal->releaseEvents());
    }

    public function test_a_trade_signal_with_a_signal_outcome_leads_to_no_order(): void
    {
        $tradeSignal = TradeSignalBuilder::rejected();

        $this->expectException(InvalidTradeSignalStateException::class);

        $tradeSignal->createOrder(
            new OrderId(OrderBuilder::ID),
            new InvestmentConfigurationId(InvestmentConfigurationBuilder::ID),
            OrderBuilder::settings(),
            new SignalOutcomeId(TradeSignalBuilder::OUTCOME_ID),
            new DateTimeImmutable(OrderBuilder::CREATED_AT),
        );
    }
}
