<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Inbox;

use DateTimeImmutable;

/**
 * One delivered message as the inbox records it: the fingerprint is a hash of the message body.
 */
final readonly class InboxEntryDto
{
    public function __construct(
        public string $consumer,
        public string $messageId,
        public string $fingerprint,
        public DateTimeImmutable $receivedAt,
    ) {}
}
