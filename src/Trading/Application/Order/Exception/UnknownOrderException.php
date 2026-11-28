<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Order\Exception;

use RuntimeException;
use Throwable;

/**
 * A decision on an order identity that names no order, such as one sent in a crafted request. Nothing changed.
 */
final class UnknownOrderException extends RuntimeException
{
    public static function withId(string $id, Throwable $previous): self
    {
        return new self(sprintf('No order has the identity "%s", so nothing changed.', $id), previous: $previous);
    }
}
