<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Inbox;

use DateTimeImmutable;

/**
 * One delivered message as the inbox records it: the body exactly as delivered, and its fingerprint, a hash of it.
 */
final readonly class InboxEntryDto
{
    public function __construct(
        public string $consumer,
        public string $messageId,
        public string $fingerprint,
        public string $body,
        public DateTimeImmutable $receivedAt,
    ) {}
}
