<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\InvestmentConfiguration\Exception;

use TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception\InvestmentConfigurationException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;

final class UnknownAnalyticsApplicationException extends InvestmentConfigurationException
{
    public static function forAnalyticsApplication(AnalyticsApplicationId $analyticsApplication): self
    {
        return new self(sprintf(
            'The analytics application "%s" is not in the registry of known analytics applications.',
            $analyticsApplication->toString(),
        ));
    }
}
