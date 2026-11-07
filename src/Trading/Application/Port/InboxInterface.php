<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Port;

use TradeManeuver\Trading\Application\Inbox\InboxDelivery;
use TradeManeuver\Trading\Application\Inbox\InboxEntryDto;

/**
 * Deduplicates deliveries per consumer and message id in the caller's transaction. The database enforces uniqueness,
 * so of two concurrent deliveries of one message id exactly one is the first.
 */
interface InboxInterface
{
    /**
     * Records the first delivery; a later one is a duplicate when its fingerprint matches, and conflicting otherwise.
     */
    public function register(InboxEntryDto $entry): InboxDelivery;
}
