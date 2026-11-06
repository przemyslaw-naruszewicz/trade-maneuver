<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\InvestmentConfiguration;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Trading\InvestmentConfiguration\Support\InvestmentConfigurationBuilder;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception\OrderAmountCurrencyMismatchException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\OrderAmount;

final class InvestmentConfigurationTest extends TestCase
{
    public function test_an_order_amount_in_another_currency_is_rejected(): void
    {
        $this->expectException(OrderAmountCurrencyMismatchException::class);
        $this->expectExceptionMessage('The order amount must be in the investment configuration currency EUR, not USD.');

        InvestmentConfigurationBuilder::anInvestmentConfiguration()
            ->withOrderAmount(OrderAmount::of('200.00', 'USD'))
            ->create();
    }
}
