<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\InvestmentConfiguration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\InvestmentConfiguration;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository\Exception\InvestmentConfigurationDoesNotExistException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository\InvestmentConfigurationRepositoryInterface;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AuthorizedAnalyticsApplications;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\BrokerageAccount;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationCurrency;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationRef;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\LimitPriceTolerance;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\OrderAmount;

final class InvestmentConfigurationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    // Seventeen integer digits: a conversion through float would change the last digits.
    private const string ORDER_AMOUNT = '12345678901234567.89';

    public function test_a_round_trip_preserves_exact_amounts_and_the_authorized_set(): void
    {
        $repository = $this->app->make(InvestmentConfigurationRepositoryInterface::class);
        $investmentConfiguration = InvestmentConfiguration::create(
            $repository->nextIdentity(),
            new InvestmentConfigurationRef('demo-investment'),
            InvestmentConfigurationCurrency::fromCode('EUR'),
            new BrokerageAccount('DEMO-ACCOUNT-1'),
            OrderAmount::of(self::ORDER_AMOUNT, 'EUR'),
            LimitPriceTolerance::fromPercentage('4.99'),
            AuthorizedAnalyticsApplications::of(
                new AnalyticsApplicationId('demo-research'),
                new AnalyticsApplicationId('demo-analytics'),
            ),
        );

        $repository->add($investmentConfiguration);
        $reloaded = $repository->get($investmentConfiguration->id());

        $state = $reloaded->state();
        $this->assertSame($investmentConfiguration->id()->toString(), $reloaded->id()->toString());
        $this->assertSame('demo-investment', $state->reference->toString());
        $this->assertSame('EUR', $state->currency->code());
        $this->assertSame('DEMO-ACCOUNT-1', $state->brokerageAccount->identifier());
        $this->assertSame(self::ORDER_AMOUNT, $state->orderAmount->amount());
        $this->assertSame('EUR', $state->orderAmount->currency()->getCurrencyCode());
        $this->assertSame('4.99', $state->limitPriceTolerance->toString());
        $this->assertSame(['demo-analytics', 'demo-research'], $state->authorizedAnalyticsApplications->identifiers());
        $this->assertSame([], $reloaded->releaseEvents());
    }

    public function test_amounts_are_stored_in_exact_numeric_columns(): void
    {
        $this->app->make(InvestmentConfigurationRepositoryInterface::class)->add(InvestmentConfiguration::create(
            $this->app->make(InvestmentConfigurationRepositoryInterface::class)->nextIdentity(),
            new InvestmentConfigurationRef('demo-investment'),
            InvestmentConfigurationCurrency::fromCode('EUR'),
            new BrokerageAccount('DEMO-ACCOUNT-1'),
            OrderAmount::of(self::ORDER_AMOUNT, 'EUR'),
            LimitPriceTolerance::fromPercentage('4.99'),
            AuthorizedAnalyticsApplications::of(),
        ));

        $stored = DB::selectOne(<<<'SQL'
            SELECT pg_typeof(order_amount)::text AS order_amount_type, order_amount::text AS order_amount,
                pg_typeof(limit_price_tolerance)::text AS tolerance_type, limit_price_tolerance::text AS tolerance
            FROM trading_investment_configurations
            SQL);

        $this->assertSame('numeric', $stored->order_amount_type);
        $this->assertSame(self::ORDER_AMOUNT, $stored->order_amount);
        $this->assertSame('numeric', $stored->tolerance_type);
        $this->assertSame('4.99', $stored->tolerance);
    }

    public function test_getting_an_unknown_investment_configuration_fails(): void
    {
        $repository = $this->app->make(InvestmentConfigurationRepositoryInterface::class);

        $this->expectException(InvestmentConfigurationDoesNotExistException::class);

        $repository->get($repository->nextIdentity());
    }
}
