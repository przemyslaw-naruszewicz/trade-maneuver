<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Order\Repository\Exception;

use RuntimeException;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;

/**
 * Another operation saved the order after it was loaded, so the stale version was not saved.
 */
final class OrderChangedConcurrentlyException extends RuntimeException
{
    public static function withId(OrderId $id, int $loadedVersion): self
    {
        return new self(sprintf('The order "%s" changed after its version %d was loaded.', $id->toString(), $loadedVersion));
    }
}
