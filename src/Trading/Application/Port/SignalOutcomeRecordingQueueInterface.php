<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Port;

use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;

/**
 * Queues the recording of the signal outcome of an order that reached a terminal status, durably in the caller's
 * transaction, so it commits with that status and still runs after a crash or restart. It may run more than once.
 */
interface SignalOutcomeRecordingQueueInterface
{
    public function enqueue(OrderId $orderId): void;
}
