<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\Outbox;

/**
 * Publishes one signal outcome from the outbox to the outcome queue its routing key selects, and reports whether the
 * message broker confirmed it.
 */
interface SignalOutcomePublisherInterface
{
    public function publish(OutboxMessageDto $message, string $routingKey): PublicationResult;
}
