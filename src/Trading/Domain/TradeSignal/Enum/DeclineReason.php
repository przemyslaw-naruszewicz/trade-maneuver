<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\Enum;

/**
 * The reason codes of a signal decline reached so far; the instrument checks and order creation add the others.
 */
enum DeclineReason: string
{
    case SignalExpired = 'SignalExpired';
    case UnsupportedDirection = 'UnsupportedDirection';
}
