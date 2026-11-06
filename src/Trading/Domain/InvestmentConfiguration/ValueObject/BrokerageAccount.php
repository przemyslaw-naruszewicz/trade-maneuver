<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject;

/**
 * A fictional account at the simulated broker, held in the investment configuration's currency.
 */
final readonly class BrokerageAccount
{
    public function __construct(private string $identifier) {}

    public function identifier(): string
    {
        return $this->identifier;
    }
}
