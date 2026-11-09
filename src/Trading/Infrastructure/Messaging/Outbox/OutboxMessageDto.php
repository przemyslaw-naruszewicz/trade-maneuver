<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\Outbox;

/**
 * One unsent outbox entry: the message as the use case committed it, and how often publishing it has failed.
 */
final readonly class OutboxMessageDto
{
    public function __construct(
        public int $id,
        public string $messageId,
        public string $messageType,
        public string $recipient,
        public string $payload,
        public int $attempts,
    ) {}
}
