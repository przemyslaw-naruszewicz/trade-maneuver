<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\InvestmentConfiguration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception\InvalidLimitPriceToleranceException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\LimitPriceTolerance;

final class LimitPriceToleranceTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function percentagesWithinBounds(): iterable
    {
        yield 'lower bound' => ['0', '0.00'];
        yield 'upper bound' => ['5', '5.00'];
        yield 'upper bound at scale' => ['5.00', '5.00'];
        yield 'between bounds' => ['1.5', '1.50'];
    }

    #[DataProvider('percentagesWithinBounds')]
    public function test_a_percentage_from_zero_to_five_inclusive_is_accepted(string $percentage, string $stored): void
    {
        $this->assertSame($stored, LimitPriceTolerance::fromPercentage($percentage)->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function percentagesOutsideBounds(): iterable
    {
        yield 'just below the lower bound' => ['-0.01'];
        yield 'just above the upper bound' => ['5.01'];
    }

    #[DataProvider('percentagesOutsideBounds')]
    public function test_a_percentage_outside_zero_to_five_is_rejected(string $percentage): void
    {
        $this->expectException(InvalidLimitPriceToleranceException::class);
        $this->expectExceptionMessage('The limit price tolerance must be from 0 % to 5 % inclusive.');

        LimitPriceTolerance::fromPercentage($percentage);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function percentagesThatAreNotPlainDecimals(): iterable
    {
        yield 'empty' => [''];
        yield 'with a percent sign' => ['1.5%'];
        yield 'exponent' => ['1e0'];
    }

    #[DataProvider('percentagesThatAreNotPlainDecimals')]
    public function test_a_percentage_that_is_not_a_plain_decimal_is_rejected(string $percentage): void
    {
        $this->expectException(InvalidLimitPriceToleranceException::class);
        $this->expectExceptionMessage('The limit price tolerance must be a decimal percentage, such as 1.50.');

        LimitPriceTolerance::fromPercentage($percentage);
    }

    public function test_a_percentage_beyond_two_decimal_places_is_rejected_without_rounding(): void
    {
        $this->expectException(InvalidLimitPriceToleranceException::class);
        $this->expectExceptionMessage('The limit price tolerance must have at most 2 decimal places.');

        LimitPriceTolerance::fromPercentage('4.999');
    }
}
