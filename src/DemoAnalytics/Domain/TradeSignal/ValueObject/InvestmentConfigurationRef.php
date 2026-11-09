<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject;

use TradeManeuver\DemoAnalytics\Domain\TradeSignal\Exception\InvalidTradeSignalException;

/**
 * The reference of the investment configuration in Trade Maneuver that a trade signal addresses.
 */
final readonly class InvestmentConfigurationRef
{
    /**
     * @throws InvalidTradeSignalException
     */
    public function __construct(private string $value)
    {
        if (trim($value) === '') {
            throw new InvalidTradeSignalException('The investment configuration reference must not be empty.');
        }
    }

    public function toString(): string
    {
        return $this->value;
    }
}
