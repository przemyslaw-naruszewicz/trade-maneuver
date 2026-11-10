<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\Enum;

/**
 * The kinds of signal outcome reached so far; evaluation and execution add the others.
 */
enum SignalOutcomeType: string
{
    case SignalRejected = 'SignalRejected';
    case SignalDeclined = 'SignalDeclined';
}
