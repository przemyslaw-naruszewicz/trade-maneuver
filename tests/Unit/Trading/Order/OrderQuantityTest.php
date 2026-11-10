<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\Order;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\OrderAmount;
use TradeManeuver\Trading\Domain\Order\Exception\InvalidOrderValueException;
use TradeManeuver\Trading\Domain\Order\ValueObject\LimitPrice;

/**
 * The quantity is the largest whole number of units whose cost at the limit price does not exceed the order amount.
 */
final class OrderQuantityTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function quantities(): iterable
    {
        yield '1000 EUR at 10.20 EUR buys 98 units for 999.60 EUR' => ['1000.00', '10.20', 98];
        yield 'an exact multiple, 1020.00 EUR at 10.20 EUR' => ['1020.00', '10.20', 100];
        yield 'one cent below the multiple, 1019.99 EUR at 10.20 EUR' => ['1019.99', '10.20', 99];
        yield 'one unit at a limit price equal to the order amount' => ['1000.00', '1000.00', 1];
        yield 'no unit at a limit price one cent above the order amount' => ['1000.00', '1000.01', 0];
    }

    #[DataProvider('quantities')]
    public function test_the_units_affordable_are_the_largest_whole_number_whose_cost_does_not_exceed_the_order_amount(
        string $orderAmount,
        string $limitPrice,
        int $expectedUnits,
    ): void {
        $units = LimitPrice::of($limitPrice, 'EUR')->unitsAffordableWith(OrderAmount::of($orderAmount, 'EUR'));

        $this->assertSame($expectedUnits, $units);
    }

    public function test_an_order_amount_in_another_currency_than_the_limit_price_is_refused(): void
    {
        $this->expectException(InvalidOrderValueException::class);
        $this->expectExceptionMessage('The order amount must be in the limit price currency EUR, not USD.');

        LimitPrice::of('10.20', 'EUR')->unitsAffordableWith(OrderAmount::of('1000.00', 'USD'));
    }
}
