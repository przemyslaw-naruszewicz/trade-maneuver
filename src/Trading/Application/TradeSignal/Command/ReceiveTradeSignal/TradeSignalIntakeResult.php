<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal;

enum TradeSignalIntakeResult
{
    /**
     * The delivery is durably accepted: stored, rejected with a signal outcome, or recognized as a duplicate.
     */
    case Accepted;

    /**
     * The message cannot be correlated, routed, or processed and receives no signal outcome; the transport sets it
     * aside for manual inspection.
     */
    case NotProcessable;
}
