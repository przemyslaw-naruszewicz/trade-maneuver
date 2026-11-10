<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\Jobs;

use Illuminate\Contracts\Bus\Dispatcher;
use TradeManeuver\Trading\Application\Port\TradeSignalEvaluationQueueInterface;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

/**
 * Queues the evaluation job on Laravel's PostgreSQL queue, which writes it on the connection of the use-case
 * transaction.
 */
final readonly class QueuedTradeSignalEvaluation implements TradeSignalEvaluationQueueInterface
{
    public function __construct(private Dispatcher $dispatcher) {}

    public function enqueue(TradeSignalId $id): void
    {
        $this->dispatcher->dispatch(new EvaluateTradeSignalJob($id->toString()));
    }
}
