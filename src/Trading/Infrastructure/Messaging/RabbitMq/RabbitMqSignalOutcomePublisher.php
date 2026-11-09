<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\RabbitMq;

use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\OutboxMessageDto;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\PublicationResult;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\SignalOutcomePublisherInterface;

final class RabbitMqSignalOutcomePublisher implements SignalOutcomePublisherInterface
{
    public function __construct(private readonly RabbitMqConnectionFactory $connections) {}

    public function publish(OutboxMessageDto $message, string $routingKey): PublicationResult
    {
        return PublicationResult::Confirmed;
    }
}
