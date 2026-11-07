<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\TradeSignal;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Trading\TradeSignal\Support\TradeSignalBuilder;
use TradeManeuver\Trading\Domain\TradeSignal\Enum\SignalOutcomeType;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalRejected;
use TradeManeuver\Trading\Domain\TradeSignal\Exception\InvalidTradeSignalValueException;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ContractViolations;

final class TradeSignalTest extends TestCase
{
    public function test_a_rejection_ends_the_trade_signal_with_a_signal_rejected_outcome_listing_the_violations(): void
    {
        $tradeSignal = TradeSignalBuilder::rejected();

        $events = $tradeSignal->releaseEvents();

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf(TradeSignalRejected::class, $event);
        $this->assertSame(TradeSignalBuilder::ID, $event->id->toString());
        $this->assertSame('demo-analytics', $event->source->toString());
        $this->assertSame(TradeSignalBuilder::OUTCOME_ID, $event->outcome->id->toString());
        $this->assertSame(SignalOutcomeType::SignalRejected, $event->outcome->type);
        $this->assertEquals(TradeSignalBuilder::violations(), $event->outcome->violations);
        $this->assertSame(TradeSignalBuilder::RECEIVED_AT, $event->outcome->occurredAt->format('Y-m-d\TH:i:s.u\Z'));
        $this->assertSame($event->outcome, $tradeSignal->outcome());
        $this->assertFalse($tradeSignal->awaitsEvaluation());
        $this->assertSame([], $tradeSignal->releaseEvents());
    }

    public function test_a_rejection_needs_at_least_one_violation(): void
    {
        $this->expectException(InvalidTradeSignalValueException::class);
        $this->expectExceptionMessage('A signal rejection needs at least one contract violation.');

        ContractViolations::of();
    }

    public function test_a_received_trade_signal_awaits_evaluation_without_an_outcome(): void
    {
        $tradeSignal = TradeSignalBuilder::received();

        $this->assertTrue($tradeSignal->awaitsEvaluation());
        $this->assertNull($tradeSignal->outcome());
    }
}
