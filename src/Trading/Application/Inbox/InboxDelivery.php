<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Inbox;

enum InboxDelivery
{
    case First;
    case Duplicate;
    case ConflictingDuplicate;
}
