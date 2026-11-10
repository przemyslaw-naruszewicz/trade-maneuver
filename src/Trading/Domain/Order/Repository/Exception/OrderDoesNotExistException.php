<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Order\Repository\Exception;

use RuntimeException;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;

final class OrderDoesNotExistException extends RuntimeException
{
    public static function withId(OrderId $id): self
    {
        return new self(sprintf('The order "%s" does not exist.', $id->toString()));
    }
}
