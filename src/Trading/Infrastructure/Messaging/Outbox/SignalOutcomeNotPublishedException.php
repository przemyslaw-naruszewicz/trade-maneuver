<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\Outbox;

use RuntimeException;

/**
 * An attempt to publish a signal outcome that RabbitMQ did not confirm. Laravel's exception handler logs it as an error
 * with the context below, and the queue retries the job.
 */
final class SignalOutcomeNotPublishedException extends RuntimeException
{
    public function __construct(private readonly OutboxMessageDto $outboxMessage, string $reason)
    {
        parent::__construct($reason);
    }

    /**
     * @return array<string, string>
     */
    public function context(): array
    {
        return [
            'messageId' => $this->outboxMessage->messageId,
            'signalId' => $this->outboxMessage->signalId,
            'recipient' => $this->outboxMessage->recipient,
        ];
    }
}
