<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\TradeSignal;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Trading\TradeSignal\Support\TradeSignalBuilder;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\TradeSignal\Dto\TradeSignalStateDto;
use TradeManeuver\Trading\Domain\TradeSignal\Enum\SignalOutcomeType;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalRejected;
use TradeManeuver\Trading\Domain\TradeSignal\Exception\InvalidTradeSignalStateException;
use TradeManeuver\Trading\Domain\TradeSignal\Exception\InvalidTradeSignalValueException;
use TradeManeuver\Trading\Domain\TradeSignal\TradeSignal;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ContractViolations;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

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

    public function test_reconstitution_keeps_the_stored_state_of_a_received_trade_signal_and_records_no_domain_events(): void
    {
        $received = TradeSignalBuilder::received();

        $reconstituted = TradeSignal::reconstitute($received->id(), $received->state());

        $this->assertSame([], $reconstituted->releaseEvents());
        $this->assertSame(TradeSignalBuilder::ID, $reconstituted->id()->toString());
        $this->assertEquals($received->state(), $reconstituted->state());
        $this->assertTrue($reconstituted->awaitsEvaluation());
    }

    public function test_reconstitution_keeps_the_stored_outcome_of_a_rejected_trade_signal_and_records_no_domain_events(): void
    {
        $rejected = TradeSignalBuilder::rejected();

        $reconstituted = TradeSignal::reconstitute($rejected->id(), $rejected->state());

        $this->assertSame([], $reconstituted->releaseEvents());
        $this->assertEquals($rejected->state(), $reconstituted->state());
        $this->assertEquals($rejected->outcome(), $reconstituted->outcome());
    }

    public function test_reconstitution_refuses_a_stored_trade_signal_without_content_or_outcome(): void
    {
        $this->expectException(InvalidTradeSignalStateException::class);
        $this->expectExceptionMessage('A trade signal without valid content must have a signal outcome.');

        TradeSignal::reconstitute(
            new TradeSignalId(TradeSignalBuilder::ID),
            new TradeSignalStateDto(new AnalyticsApplicationId('demo-analytics'), new DateTimeImmutable(TradeSignalBuilder::RECEIVED_AT), null, null),
        );
    }
}
