<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject;

/**
 * The identifier an analytics application sends as `source`.
 */
final readonly class AnalyticsApplicationId
{
    public function __construct(private string $value) {}

    public function toString(): string
    {
        return $this->value;
    }
}
