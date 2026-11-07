<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\Enum;

enum Direction: string
{
    case Long = 'long';
    case Short = 'short';
}
