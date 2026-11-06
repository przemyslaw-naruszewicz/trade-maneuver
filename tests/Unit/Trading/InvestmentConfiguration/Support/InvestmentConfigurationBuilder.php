<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\InvestmentConfiguration\Support;

use TradeManeuver\Trading\Domain\InvestmentConfiguration\InvestmentConfiguration;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AuthorizedAnalyticsApplications;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\BrokerageAccount;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationCurrency;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationRef;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\LimitPriceTolerance;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\OrderAmount;

/**
 * Creates a valid investment configuration; each `with` method changes one value.
 */
final class InvestmentConfigurationBuilder
{
    public const string ID = '019a5a6e-0000-7000-8000-000000000001';

    private OrderAmount $orderAmount;

    private function __construct()
    {
        $this->orderAmount = OrderAmount::of('200.00', 'EUR');
    }

    public static function anInvestmentConfiguration(): self
    {
        return new self;
    }

    public function withOrderAmount(OrderAmount $orderAmount): self
    {
        $this->orderAmount = $orderAmount;

        return $this;
    }

    public function create(): InvestmentConfiguration
    {
        return InvestmentConfiguration::create(
            new InvestmentConfigurationId(self::ID),
            new InvestmentConfigurationRef('demo-investment'),
            InvestmentConfigurationCurrency::fromCode('EUR'),
            new BrokerageAccount('DEMO-ACCOUNT-1'),
            $this->orderAmount,
            LimitPriceTolerance::fromPercentage('1.50'),
            AuthorizedAnalyticsApplications::of(new AnalyticsApplicationId('demo-analytics')),
        );
    }
}
