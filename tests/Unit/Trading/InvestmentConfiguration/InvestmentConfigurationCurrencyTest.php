<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\InvestmentConfiguration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception\UnsupportedCurrencyException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationCurrency;

final class InvestmentConfigurationCurrencyTest extends TestCase
{
    public function test_eur_is_accepted(): void
    {
        $this->assertSame('EUR', InvestmentConfigurationCurrency::fromCode('EUR')->code());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function currenciesOtherThanEur(): iterable
    {
        yield 'another known currency' => ['USD'];
        yield 'lowercase eur' => ['eur'];
        yield 'an unknown code' => ['XYZ'];
        yield 'empty' => [''];
    }

    #[DataProvider('currenciesOtherThanEur')]
    public function test_a_currency_other_than_eur_is_rejected(string $code): void
    {
        $this->expectException(UnsupportedCurrencyException::class);
        $this->expectExceptionMessage(sprintf('The investment configuration currency must be EUR, not "%s".', $code));

        InvestmentConfigurationCurrency::fromCode($code);
    }
}
