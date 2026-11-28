<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\TradeSignal;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Trading\TradeSignal\Support\TradeSignalBuilder;
use TradeManeuver\Trading\Domain\Order\Enum\OrderStatus;
use TradeManeuver\Trading\Domain\TradeSignal\Enum\DeclineReason;
use TradeManeuver\Trading\Domain\TradeSignal\Enum\SignalOutcomeType;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalDeclined;
use TradeManeuver\Trading\Domain\TradeSignal\Exception\InvalidTradeSignalStateException;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcomeId;

/**
 * The signal outcome that a trade signal gets when its order reaches a terminal status.
 */
final class TradeSignalEndAfterOrderTest extends TestCase
{
    private const string ENDED_AT = '2026-11-09T11:00:00Z';

    public function test_an_order_refused_by_the_operator_declines_its_trade_signal_with_the_reason_refused_by_operator(): void
    {
        $tradeSignal = TradeSignalBuilder::received();
        $tradeSignal->releaseEvents();

        $tradeSignal->endAfterOrder(OrderStatus::RefusedByOperator, new SignalOutcomeId(TradeSignalBuilder::OUTCOME_ID), new DateTimeImmutable(self::ENDED_AT));

        $outcome = $tradeSignal->outcome();
        $this->assertNotNull($outcome);
        $this->assertSame(SignalOutcomeType::SignalDeclined, $outcome->type);
        $this->assertSame(DeclineReason::RefusedByOperator, $outcome->reason);
        $this->assertSame(TradeSignalBuilder::OUTCOME_ID, $outcome->id->toString());
        $this->assertEquals(new DateTimeImmutable(self::ENDED_AT), $outcome->occurredAt);
        $events = $tradeSignal->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TradeSignalDeclined::class, $events[0]);
        $this->assertSame(DeclineReason::RefusedByOperator, $events[0]->reason);
    }

    /**
     * @return iterable<string, array{OrderStatus}>
     */
    public static function statusesWithoutSignalOutcome(): iterable
    {
        yield 'awaiting approval' => [OrderStatus::AwaitingApproval];
        yield 'approved' => [OrderStatus::Approved];
    }

    #[DataProvider('statusesWithoutSignalOutcome')]
    public function test_an_order_status_without_a_signal_outcome_is_refused(OrderStatus $status): void
    {
        $tradeSignal = TradeSignalBuilder::received();
        $tradeSignal->releaseEvents();

        $this->expectException(InvalidTradeSignalStateException::class);

        try {
            $tradeSignal->endAfterOrder($status, new SignalOutcomeId(TradeSignalBuilder::OUTCOME_ID), new DateTimeImmutable(self::ENDED_AT));
        } finally {
            $this->assertNull($tradeSignal->outcome());
            $this->assertSame([], $tradeSignal->releaseEvents());
        }
    }

    public function test_a_trade_signal_that_has_a_signal_outcome_is_not_ended_again(): void
    {
        $tradeSignal = TradeSignalBuilder::rejected();
        $outcome = $tradeSignal->outcome();
        $tradeSignal->releaseEvents();

        $this->expectException(InvalidTradeSignalStateException::class);

        try {
            $tradeSignal->endAfterOrder(OrderStatus::RefusedByOperator, new SignalOutcomeId('019a5a6e-0000-7000-8000-0000000000b2'), new DateTimeImmutable(self::ENDED_AT));
        } finally {
            $this->assertSame($outcome, $tradeSignal->outcome());
            $this->assertSame([], $tradeSignal->releaseEvents());
        }
    }
}
