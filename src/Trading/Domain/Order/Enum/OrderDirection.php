<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Order\Enum;

/**
 * Whether an order buys or sells; only buy orders exist so far, from long trade signals.
 */
enum OrderDirection: string
{
    case Buy = 'buy';
}
