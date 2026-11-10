<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Port;

use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

/**
 * Queues the evaluation of an accepted trade signal durably in the caller's transaction, so it commits with the intake
 * and still runs after a crash or restart. An evaluation may run more than once.
 */
interface TradeSignalEvaluationQueueInterface
{
    public function enqueue(TradeSignalId $id): void;
}
