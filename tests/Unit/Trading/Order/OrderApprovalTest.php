<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\Order;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Trading\Order\Support\OrderBuilder;
use Tests\Unit\Trading\TradeSignal\Support\TradeSignalBuilder;
use Throwable;
use TradeManeuver\Trading\Domain\Order\Enum\OrderStatus;
use TradeManeuver\Trading\Domain\Order\Exception\ApprovalLeavesZeroQuantityException;
use TradeManeuver\Trading\Domain\Order\Exception\InvalidOrderValueException;
use TradeManeuver\Trading\Domain\Order\Order;
use TradeManeuver\Trading\Domain\Order\ValueObject\LimitPrice;

/**
 * The operator's approval of the order of the builder: the limit price 10.20 EUR and the quantity 98 from the order
 * amount 1000.00 EUR, with signal validity until 10 Nov 2026, 16:00 UTC.
 */
final class OrderApprovalTest extends TestCase
{
    private const string APPROVED_AT = '2026-11-09T11:00:00Z';

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidNewLimitPrices(): iterable
    {
        yield 'zero, 0.00 EUR' => ['0.00', 'EUR'];
        yield 'negative, -1.00 EUR' => ['-1.00', 'EUR'];
        yield 'in another currency, 10.60 USD' => ['10.60', 'USD'];
        yield 'with three decimal places, 10.505 EUR' => ['10.505', 'EUR'];
    }

    #[DataProvider('invalidNewLimitPrices')]
    public function test_an_invalid_new_limit_price_is_refused_and_the_order_stays_awaiting_approval(string $amount, string $currencyCode): void
    {
        $order = OrderBuilder::awaitingApproval();

        $refusal = null;
        try {
            $order->approve(LimitPrice::of($amount, $currencyCode), $this->validUntil(), new DateTimeImmutable(self::APPROVED_AT));
        } catch (Throwable $caught) {
            $refusal = $caught;
        }

        $this->assertInstanceOf(InvalidOrderValueException::class, $refusal);
        $this->assertAwaitsApprovalUnchanged($order);
    }

    public function test_a_new_limit_price_of_one_cent_is_accepted(): void
    {
        $order = OrderBuilder::awaitingApproval();

        $order->approve(LimitPrice::of('0.01', 'EUR'), $this->validUntil(), new DateTimeImmutable(self::APPROVED_AT));

        $state = $order->state();
        $this->assertSame(OrderStatus::Approved, $state->status);
        $this->assertSame('0.01', $state->limitPrice->amount());
        $this->assertSame(100000, $state->quantity->units());
    }

    public function test_approving_without_a_new_limit_price_keeps_the_limit_price_and_the_quantity(): void
    {
        $order = OrderBuilder::awaitingApproval();

        $order->approve(null, $this->validUntil(), new DateTimeImmutable(self::APPROVED_AT));

        $state = $order->state();
        $this->assertSame(OrderStatus::Approved, $state->status);
        $this->assertSame('10.20', $state->limitPrice->amount());
        $this->assertSame(98, $state->quantity->units());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function recalculatedQuantities(): iterable
    {
        yield '1000.00 EUR at 10.50 EUR, rounded down from 95.238' => ['10.50', 95];
        yield '1000.00 EUR at 12.50 EUR, exactly 80' => ['12.50', 80];
        yield '1000.00 EUR at 1000.00 EUR, one unit' => ['1000.00', 1];
    }

    #[DataProvider('recalculatedQuantities')]
    public function test_a_new_limit_price_recalculates_the_quantity_from_the_order_amount_of_the_snapshot(string $newLimitPrice, int $quantity): void
    {
        $order = OrderBuilder::awaitingApproval();

        $order->approve(LimitPrice::of($newLimitPrice, 'EUR'), $this->validUntil(), new DateTimeImmutable(self::APPROVED_AT));

        $state = $order->state();
        $this->assertSame($newLimitPrice, $state->limitPrice->amount());
        $this->assertSame($quantity, $state->quantity->units());
        $this->assertSame('1000.00', $state->settings->orderAmount->amount());
    }

    public function test_a_new_limit_price_that_leaves_a_quantity_of_zero_is_refused_and_the_order_stays_awaiting_approval(): void
    {
        $order = OrderBuilder::awaitingApproval();

        $refusal = null;
        try {
            // 1000.00 EUR buys no unit at 1000.01 EUR.
            $order->approve(LimitPrice::of('1000.01', 'EUR'), $this->validUntil(), new DateTimeImmutable(self::APPROVED_AT));
        } catch (Throwable $caught) {
            $refusal = $caught;
        }

        $this->assertInstanceOf(ApprovalLeavesZeroQuantityException::class, $refusal);
        $this->assertAwaitsApprovalUnchanged($order);
    }

    private function validUntil(): DateTimeImmutable
    {
        return new DateTimeImmutable(TradeSignalBuilder::VALID_UNTIL);
    }

    private function assertAwaitsApprovalUnchanged(Order $order): void
    {
        $state = $order->state();
        $this->assertSame(OrderStatus::AwaitingApproval, $state->status);
        $this->assertSame('10.20', $state->limitPrice->amount());
        $this->assertSame(98, $state->quantity->units());
    }
}
