<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\Outbox;

use Illuminate\Database\ConnectionInterface;
use Psr\Clock\ClockInterface;
use TradeManeuver\Trading\Application\IntegrationEvent\SignalOutcomeIntegrationEvent;
use TradeManeuver\Trading\Application\Port\IntegrationEventPublisherInterface;

/**
 * Writes the message to the transactional outbox on the connection of the use-case transaction; a dispatcher
 * publishes committed entries later.
 */
final readonly class OutboxIntegrationEventPublisher implements IntegrationEventPublisherInterface
{
    public function __construct(private ConnectionInterface $connection, private ClockInterface $clock) {}

    public function publish(SignalOutcomeIntegrationEvent $event): void
    {
        $this->connection->table('trading_outbox_messages')->insert([
            'message_id' => $event->id,
            'message_type' => SignalOutcomeIntegrationEvent::TYPE,
            'recipient' => $event->recipient,
            'payload' => json_encode($event->payload(), JSON_THROW_ON_ERROR),
            'recorded_at' => $this->clock->now()->format('Y-m-d H:i:s.uP'),
        ]);
    }
}
