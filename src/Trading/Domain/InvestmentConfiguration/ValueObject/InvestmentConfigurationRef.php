<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject;

use TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception\InvalidInvestmentConfigurationRefException;

/**
 * The reference that trade signals use as `investmentConfigurationRef`.
 */
final readonly class InvestmentConfigurationRef
{
    /**
     * @throws InvalidInvestmentConfigurationRefException
     */
    public function __construct(private string $value)
    {
        if (trim($value) === '') {
            throw new InvalidInvestmentConfigurationRefException('The investment configuration reference must not be empty.');
        }
    }

    public function toString(): string
    {
        return $this->value;
    }
}
