<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject;

/**
 * The settings of an investment configuration that an order uses. An order keeps them as a snapshot.
 */
final readonly class OrderSettings
{
    public function __construct(
        public OrderAmount $orderAmount,
        public LimitPriceTolerance $limitPriceTolerance,
        public InvestmentConfigurationCurrency $currency,
        public BrokerageAccount $brokerageAccount,
    ) {}
}
