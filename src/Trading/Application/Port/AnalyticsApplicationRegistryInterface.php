<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Port;

use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;

/**
 * The known analytics applications, supplied as deployment configuration.
 */
interface AnalyticsApplicationRegistryInterface
{
    public function isKnown(AnalyticsApplicationId $analyticsApplication): bool;
}
