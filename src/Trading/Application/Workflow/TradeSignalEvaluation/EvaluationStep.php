<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Workflow\TradeSignalEvaluation;

/**
 * The steps of a trade signal's evaluation, in the order they run.
 */
enum EvaluationStep: string
{
    case ValidityAndDirection = 'validity_and_direction';
    case InstrumentChecks = 'instrument_checks';
    case OrderCreation = 'order_creation';
}
