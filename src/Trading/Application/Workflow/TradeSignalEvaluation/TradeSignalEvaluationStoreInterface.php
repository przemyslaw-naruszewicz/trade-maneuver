<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Workflow\TradeSignalEvaluation;

use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

/**
 * Keeps the progress of trade signal evaluations. It writes in the caller's transaction, so a passed step commits
 * with the step's other changes.
 */
interface TradeSignalEvaluationStoreInterface
{
    public function progressOf(TradeSignalId $tradeSignalId): TradeSignalEvaluationProgress;

    public function save(TradeSignalEvaluationProgress $progress): void;
}
