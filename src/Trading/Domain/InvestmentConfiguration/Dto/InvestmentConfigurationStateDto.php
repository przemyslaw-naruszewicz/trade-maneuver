<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\Dto;

use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AuthorizedAnalyticsApplications;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\BrokerageAccount;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationCurrency;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationRef;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\LimitPriceTolerance;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\OrderAmount;

final readonly class InvestmentConfigurationStateDto
{
    public function __construct(
        public InvestmentConfigurationRef $reference,
        public InvestmentConfigurationCurrency $currency,
        public BrokerageAccount $brokerageAccount,
        public OrderAmount $orderAmount,
        public LimitPriceTolerance $limitPriceTolerance,
        public AuthorizedAnalyticsApplications $authorizedAnalyticsApplications,
    ) {}
}
