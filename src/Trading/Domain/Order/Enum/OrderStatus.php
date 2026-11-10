<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Order\Enum;

/**
 * The order statuses reached so far; approval, submission and execution add the others.
 */
enum OrderStatus: string
{
    case AwaitingApproval = 'awaiting_approval';
}
