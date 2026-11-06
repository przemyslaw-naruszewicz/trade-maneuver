<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\InvestmentConfiguration\Command\CreateInvestmentConfiguration;

final readonly class CreateInvestmentConfigurationCommand
{
    /**
     * @param  list<string>  $authorizedAnalyticsApplications
     */
    public function __construct(
        public string $actor,
        public string $reference,
        public string $currency,
        public string $brokerageAccount,
        public string $orderAmount,
        public string $orderAmountCurrency,
        public string $limitPriceTolerance,
        public array $authorizedAnalyticsApplications,
    ) {}
}
