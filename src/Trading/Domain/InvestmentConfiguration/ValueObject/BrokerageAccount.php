<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject;

use TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception\InvalidBrokerageAccountException;

/**
 * A fictional account at the simulated broker, held in the investment configuration's currency.
 */
final readonly class BrokerageAccount
{
    /**
     * @throws InvalidBrokerageAccountException
     */
    public function __construct(private string $identifier)
    {
        if (trim($identifier) === '') {
            throw new InvalidBrokerageAccountException('The brokerage account must not be empty.');
        }
    }

    public function identifier(): string
    {
        return $this->identifier;
    }
}
