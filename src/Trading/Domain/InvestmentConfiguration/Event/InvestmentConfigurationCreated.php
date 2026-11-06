<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\Event;

use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AuthorizedAnalyticsApplications;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\BrokerageAccount;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationCurrency;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationRef;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\LimitPriceTolerance;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\OrderAmount;

final readonly class InvestmentConfigurationCreated
{
    public function __construct(
        public InvestmentConfigurationId $id,
        public InvestmentConfigurationRef $reference,
        public InvestmentConfigurationCurrency $currency,
        public BrokerageAccount $brokerageAccount,
        public OrderAmount $orderAmount,
        public LimitPriceTolerance $limitPriceTolerance,
        public AuthorizedAnalyticsApplications $authorizedAnalyticsApplications,
    ) {}
}
