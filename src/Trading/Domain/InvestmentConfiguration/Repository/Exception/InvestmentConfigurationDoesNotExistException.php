<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository\Exception;

use RuntimeException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationId;

final class InvestmentConfigurationDoesNotExistException extends RuntimeException
{
    public static function withId(InvestmentConfigurationId $id): self
    {
        return new self(sprintf('The investment configuration "%s" does not exist.', $id->toString()));
    }
}
