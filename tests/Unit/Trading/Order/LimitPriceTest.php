<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\Order;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\LimitPriceTolerance;
use TradeManeuver\Trading\Domain\Order\Exception\InvalidOrderValueException;
use TradeManeuver\Trading\Domain\Order\ValueObject\LimitPrice;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ReferencePrice;

final class LimitPriceTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function proposals(): iterable
    {
        yield 'an exact result, 10.00 EUR at 2 %' => ['10.00', '2.00', '10.20'];
        yield 'rounded down from 12.530175, 12.345 EUR at 1.5 %' => ['12.345', '1.50', '12.53'];
        yield 'tolerance 0 % keeps the reference price' => ['10.00', '0', '10.00'];
        yield 'tolerance 0 % rounds a finer reference price down' => ['12.349', '0', '12.34'];
        yield 'rounded down from 1000.089999, 999.99 EUR at 0.01 %' => ['999.99', '0.01', '1000.08'];
        yield 'the highest tolerance, 5 %' => ['20.00', '5.00', '21.00'];
    }

    #[DataProvider('proposals')]
    public function test_the_proposed_limit_price_is_the_reference_price_plus_the_tolerance_rounded_down_to_the_minor_unit(
        string $referencePrice,
        string $tolerance,
        string $expectedLimitPrice,
    ): void {
        $limitPrice = LimitPrice::proposedFor(
            ReferencePrice::of($referencePrice, 'EUR', '2026-11-07T08:59:00Z'),
            LimitPriceTolerance::fromPercentage($tolerance),
        );

        $this->assertSame($expectedLimitPrice, $limitPrice->amount());
        $this->assertSame('EUR', $limitPrice->currencyCode());
    }

    public function test_a_reference_price_below_the_minor_unit_gives_no_limit_price(): void
    {
        $this->expectException(InvalidOrderValueException::class);
        $this->expectExceptionMessage('The limit price must be greater than zero, not 0.00 EUR.');

        LimitPrice::proposedFor(ReferencePrice::of('0.004', 'EUR', '2026-11-07T08:59:00Z'), LimitPriceTolerance::fromPercentage('5.00'));
    }

    public function test_a_stored_limit_price_of_one_cent_is_the_smallest(): void
    {
        $this->assertSame('0.01', LimitPrice::of('0.01', 'EUR')->amount());
    }
}
