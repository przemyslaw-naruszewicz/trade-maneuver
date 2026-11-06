<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject;

final readonly class InvestmentConfigurationRef
{
    public function __construct(private string $value) {}

    public function toString(): string
    {
        return $this->value;
    }
}
