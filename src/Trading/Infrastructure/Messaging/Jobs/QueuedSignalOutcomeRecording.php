<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\Jobs;

use Illuminate\Contracts\Bus\Dispatcher;
use TradeManeuver\Trading\Application\Port\SignalOutcomeRecordingQueueInterface;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;

/**
 * Queues the recording job on Laravel's PostgreSQL queue, which writes it on the connection of the use-case
 * transaction.
 */
final readonly class QueuedSignalOutcomeRecording implements SignalOutcomeRecordingQueueInterface
{
    public function __construct(private Dispatcher $dispatcher) {}

    public function enqueue(OrderId $orderId): void
    {
        $this->dispatcher->dispatch(new RecordSignalOutcomeJob($orderId->toString()));
    }
}
