<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Order\Exception;

use RuntimeException;
use Throwable;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;

/**
 * A decision on an order that another decision reached first, before or while it was made. Nothing changed.
 */
final class OrderAlreadyDecidedException extends RuntimeException
{
    public static function forOrder(OrderId $id, Throwable $previous): self
    {
        return new self(sprintf('The order "%s" was already decided, so nothing changed.', $id->toString()), 0, $previous);
    }
}
