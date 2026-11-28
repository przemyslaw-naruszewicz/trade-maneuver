<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use TradeManeuver\Trading\Application\Workflow\SignalOutcomeRecording\SignalOutcomeRecordingOrchestrator;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;

/**
 * Records the signal outcome of one order that reached a terminal status. The job is queued on the `database`
 * connection in the transaction that ends the order, so it commits or rolls back with it, and a crash after the commit
 * leaves the job to run later. A job that a crashed worker had reserved becomes available again after the queue's
 * `retry_after`, and the step skips a trade signal that already has its signal outcome.
 */
final class RecordSignalOutcomeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public int $backoff = 30;

    public function __construct(public readonly string $orderId)
    {
        $this->onConnection('database');
    }

    public function handle(SignalOutcomeRecordingOrchestrator $orchestrator): void
    {
        $orchestrator->record(new OrderId($this->orderId));
    }
}
