<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\Order;

use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Trading\Order\Support\OrderBuilder;
use Tests\Unit\Trading\TradeSignal\Support\TradeSignalBuilder;
use Throwable;
use TradeManeuver\Trading\Domain\Order\Enum\OrderStatus;
use TradeManeuver\Trading\Domain\Order\Event\OrderApproved;
use TradeManeuver\Trading\Domain\Order\Event\OrderRefused;
use TradeManeuver\Trading\Domain\Order\Exception\OrderNotAwaitingApprovalException;
use TradeManeuver\Trading\Domain\Order\Order;
use TradeManeuver\Trading\Domain\Order\ValueObject\LimitPrice;

/**
 * The operator's decisions are allowed only on an order awaiting approval: approval makes it approved, and refusal
 * ends it as refused by the operator.
 */
final class OrderDecisionTransitionsTest extends TestCase
{
    private const string DECIDED_AT = '2026-11-09T11:00:00.250000Z';

    public function test_an_order_awaiting_approval_can_be_approved(): void
    {
        $order = $this->orderAwaitingApproval();

        $order->approve(LimitPrice::of('10.50', 'EUR'), new DateTimeImmutable(TradeSignalBuilder::VALID_UNTIL), new DateTimeImmutable(self::DECIDED_AT));

        $state = $order->state();
        $this->assertSame(OrderStatus::Approved, $state->status);
        $this->assertNull($state->endedAt, 'An approved order has not ended.');
        $events = $order->releaseEvents();
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf(OrderApproved::class, $event);
        $this->assertSame(OrderBuilder::ID, $event->id->toString());
        $this->assertSame(TradeSignalBuilder::ID, $event->tradeSignalId->toString());
        $this->assertSame('10.20', $event->previousLimitPrice->amount());
        $this->assertSame('10.50', $event->limitPrice->amount());
        $this->assertSame(98, $event->previousQuantity->units());
        $this->assertSame(95, $event->quantity->units());
        $this->assertSame(self::DECIDED_AT, $event->approvedAt->format('Y-m-d\TH:i:s.u\Z'));
    }

    public function test_an_order_awaiting_approval_can_be_refused_which_ends_it(): void
    {
        $order = $this->orderAwaitingApproval();

        $order->refuse(new DateTimeImmutable(self::DECIDED_AT));

        $state = $order->state();
        $this->assertSame(OrderStatus::RefusedByOperator, $state->status);
        $this->assertSame('10.20', $state->limitPrice->amount());
        $this->assertSame(98, $state->quantity->units());
        $this->assertNotNull($state->endedAt);
        $this->assertSame(self::DECIDED_AT, $state->endedAt->format('Y-m-d\TH:i:s.u\Z'));
        $events = $order->releaseEvents();
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf(OrderRefused::class, $event);
        $this->assertSame(OrderBuilder::ID, $event->id->toString());
        $this->assertSame(TradeSignalBuilder::ID, $event->tradeSignalId->toString());
        $this->assertSame('10.20', $event->limitPrice->amount());
        $this->assertSame(98, $event->quantity->units());
        $this->assertSame(self::DECIDED_AT, $event->refusedAt->format('Y-m-d\TH:i:s.u\Z'));
    }

    /**
     * @return iterable<string, array{Closure(Order): void, Closure(Order): void}>
     */
    public static function decisionsAfterADecision(): iterable
    {
        $approve = static fn (Order $order) => $order->approve(null, new DateTimeImmutable(TradeSignalBuilder::VALID_UNTIL), new DateTimeImmutable(self::DECIDED_AT));
        $refuse = static fn (Order $order) => $order->refuse(new DateTimeImmutable(self::DECIDED_AT));

        yield 'approving an approved order' => [$approve, $approve];
        yield 'refusing an approved order' => [$approve, $refuse];
        yield 'approving a refused order' => [$refuse, $approve];
        yield 'refusing a refused order' => [$refuse, $refuse];
    }

    /**
     * @param  Closure(Order): void  $firstDecision
     * @param  Closure(Order): void  $secondDecision
     */
    #[DataProvider('decisionsAfterADecision')]
    public function test_an_order_that_is_no_longer_awaiting_approval_cannot_be_decided_again(Closure $firstDecision, Closure $secondDecision): void
    {
        $order = $this->orderAwaitingApproval();
        $firstDecision($order);
        $order->releaseEvents();
        $decided = $order->state();

        $refusal = null;
        try {
            $secondDecision($order);
        } catch (Throwable $caught) {
            $refusal = $caught;
        }

        $this->assertInstanceOf(OrderNotAwaitingApprovalException::class, $refusal);
        $this->assertEquals($decided, $order->state());
        $this->assertSame([], $order->releaseEvents());
    }

    private function orderAwaitingApproval(): Order
    {
        $order = OrderBuilder::awaitingApproval();
        $order->releaseEvents();

        return $order;
    }
}
