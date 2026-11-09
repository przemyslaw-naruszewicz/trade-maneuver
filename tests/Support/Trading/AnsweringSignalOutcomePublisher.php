<?php

declare(strict_types=1);

namespace Tests\Support\Trading;

use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\OutboxMessageDto;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\PublicationResult;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\SignalOutcomePublisherInterface;

/**
 * Stands in for RabbitMQ with a fixed publication result, such as a confirmation that never arrives.
 */
final readonly class AnsweringSignalOutcomePublisher implements SignalOutcomePublisherInterface
{
    public function __construct(private PublicationResult $result) {}

    public function publish(OutboxMessageDto $message, string $routingKey): PublicationResult
    {
        return $this->result;
    }
}
