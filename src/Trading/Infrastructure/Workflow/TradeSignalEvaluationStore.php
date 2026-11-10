<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Workflow;

use Illuminate\Database\ConnectionInterface;
use TradeManeuver\Trading\Application\Workflow\TradeSignalEvaluation\EvaluationStep;
use TradeManeuver\Trading\Application\Workflow\TradeSignalEvaluation\TradeSignalEvaluationProgress;
use TradeManeuver\Trading\Application\Workflow\TradeSignalEvaluation\TradeSignalEvaluationStoreInterface;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

/**
 * One row per passed step. The primary key refuses a step passed twice, and the orchestrator's lock on the trade
 * signal keeps two evaluations from passing the same step.
 */
final readonly class TradeSignalEvaluationStore implements TradeSignalEvaluationStoreInterface
{
    private const string TABLE = 'trading_trade_signal_evaluation_steps';

    public function __construct(private ConnectionInterface $connection) {}

    public function progressOf(TradeSignalId $tradeSignalId): TradeSignalEvaluationProgress
    {
        $passedSteps = [];
        $rows = $this->connection->table(self::TABLE)->where('trade_signal_id', $tradeSignalId->toString())->pluck('step');
        foreach ($rows as $step) {
            $passedSteps[] = EvaluationStep::from((string) $step);
        }

        return TradeSignalEvaluationProgress::of($tradeSignalId, $passedSteps);
    }

    public function save(TradeSignalEvaluationProgress $progress): void
    {
        foreach ($progress->newlyPassed() as $step => $passedAt) {
            $this->connection->table(self::TABLE)->insert([
                'trade_signal_id' => $progress->tradeSignalId()->toString(),
                'step' => $step,
                'passed_at' => $passedAt->format('Y-m-d H:i:s.uP'),
            ]);
        }
    }
}
