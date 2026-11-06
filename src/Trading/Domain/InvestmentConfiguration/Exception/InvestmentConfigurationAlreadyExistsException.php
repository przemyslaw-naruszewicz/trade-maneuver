<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception;

final class InvestmentConfigurationAlreadyExistsException extends InvestmentConfigurationException
{
    public static function create(): self
    {
        return new self('An investment configuration already exists.');
    }
}
