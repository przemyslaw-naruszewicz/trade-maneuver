<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Port;

use TradeManeuver\Trading\Application\DomainEvent\DomainEventContext;

/**
 * Hands the domain events that an aggregate released to their local handlers, synchronously, in the caller's
 * transaction. A handler's failure reaches the caller and rolls the transaction back.
 */
interface DomainEventBusInterface
{
    public function dispatch(DomainEventContext $context, object ...$events): void;
}
