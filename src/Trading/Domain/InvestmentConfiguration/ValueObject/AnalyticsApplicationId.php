<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject;

use TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception\InvalidAnalyticsApplicationIdException;

/**
 * The identifier an analytics application sends as `source`.
 */
final readonly class AnalyticsApplicationId
{
    /**
     * @throws InvalidAnalyticsApplicationIdException
     */
    public function __construct(private string $value)
    {
        if (trim($value) === '') {
            throw new InvalidAnalyticsApplicationIdException('The analytics application identifier must not be empty.');
        }
    }

    public function toString(): string
    {
        return $this->value;
    }
}
