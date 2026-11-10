<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Order\Enum;

/**
 * How much of an order's quantity the broker has executed. Execution adds partial and complete progress, with the
 * executed quantity.
 */
enum ExecutionProgress: string
{
    case None = 'none';
}
