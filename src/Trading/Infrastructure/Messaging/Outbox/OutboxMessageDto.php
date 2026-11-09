<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\Outbox;

/**
 * One unsent outbox entry: the message as the use case committed it, and the trade signal its payload names.
 */
final readonly class OutboxMessageDto
{
    public function __construct(
        public int $id,
        public string $messageId,
        public string $messageType,
        public string $recipient,
        public string $payload,
        public string $signalId,
    ) {}
}
