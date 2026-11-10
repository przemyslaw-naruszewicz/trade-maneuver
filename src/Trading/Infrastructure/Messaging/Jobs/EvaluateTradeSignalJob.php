<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use TradeManeuver\Trading\Application\Workflow\TradeSignalEvaluation\TradeSignalEvaluationOrchestrator;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

/**
 * Evaluates one accepted trade signal. The job is queued on the `database` connection in the intake transaction, so
 * it commits or rolls back with the trade signal. A job that a crashed worker had reserved becomes available again
 * after the queue's `retry_after`.
 */
final class EvaluateTradeSignalJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $tradeSignalId)
    {
        $this->onConnection('database');
    }

    public function handle(TradeSignalEvaluationOrchestrator $orchestrator): void
    {
        $orchestrator->evaluate(new TradeSignalId($this->tradeSignalId));
    }
}
