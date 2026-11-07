<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\Repository\Exception;

use RuntimeException;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

final class TradeSignalDoesNotExistException extends RuntimeException
{
    public static function withId(TradeSignalId $id): self
    {
        return new self(sprintf('The trade signal "%s" does not exist.', $id->toString()));
    }
}
