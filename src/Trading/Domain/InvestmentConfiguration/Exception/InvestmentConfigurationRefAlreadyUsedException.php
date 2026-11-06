<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception;

use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationRef;

final class InvestmentConfigurationRefAlreadyUsedException extends InvestmentConfigurationException
{
    public static function forReference(InvestmentConfigurationRef $reference): self
    {
        return new self(sprintf('The investment configuration reference "%s" is already used.', $reference->toString()));
    }
}
