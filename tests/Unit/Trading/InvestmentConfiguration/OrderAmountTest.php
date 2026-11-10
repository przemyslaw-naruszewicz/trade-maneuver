<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\InvestmentConfiguration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception\InvalidOrderAmountException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\OrderAmount;

final class OrderAmountTest extends TestCase
{
    public function test_the_smallest_positive_amount_is_accepted(): void
    {
        $orderAmount = OrderAmount::of('0.01', 'EUR');

        $this->assertSame('0.01', $orderAmount->amount());
        $this->assertSame('EUR', $orderAmount->currency()->getCurrencyCode());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function amountsNotGreaterThanZero(): iterable
    {
        yield 'zero' => ['0'];
        yield 'zero at the currency scale' => ['0.00'];
        yield 'negative' => ['-0.01'];
    }

    #[DataProvider('amountsNotGreaterThanZero')]
    public function test_an_amount_not_greater_than_zero_is_rejected(string $amount): void
    {
        $this->expectException(InvalidOrderAmountException::class);
        $this->expectExceptionMessage('The order amount must be greater than zero.');

        OrderAmount::of($amount, 'EUR');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function amountsThatAreNotPlainDecimals(): iterable
    {
        yield 'empty' => [''];
        yield 'text' => ['two hundred'];
        yield 'exponent' => ['2e2'];
        yield 'fraction' => ['400/2'];
        yield 'surrounding whitespace' => [' 200.00'];
        yield 'missing integer part' => ['.50'];
    }

    #[DataProvider('amountsThatAreNotPlainDecimals')]
    public function test_an_amount_that_is_not_a_plain_decimal_is_rejected(string $amount): void
    {
        $this->expectException(InvalidOrderAmountException::class);
        $this->expectExceptionMessage('The order amount must be a decimal number, such as 200.00.');

        OrderAmount::of($amount, 'EUR');
    }

    public function test_an_amount_beyond_the_currency_scale_is_rejected_without_rounding(): void
    {
        $this->expectException(InvalidOrderAmountException::class);
        $this->expectExceptionMessage('The order amount must have at most 2 decimal places in EUR.');

        OrderAmount::of('200.001', 'EUR');
    }

    public function test_an_amount_in_an_unknown_currency_is_rejected(): void
    {
        $this->expectException(InvalidOrderAmountException::class);
        $this->expectExceptionMessage('The currency "DEMO" is unknown.');

        OrderAmount::of('200.00', 'DEMO');
    }

    public function test_an_amount_is_kept_exactly_at_the_currency_scale(): void
    {
        $this->assertSame('12345678901234567.89', OrderAmount::of('12345678901234567.89', 'EUR')->amount());
        $this->assertSame('200.50', OrderAmount::of('200.5', 'EUR')->amount());
    }
}
