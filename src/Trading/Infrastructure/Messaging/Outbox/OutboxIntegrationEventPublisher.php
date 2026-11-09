<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\Outbox;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Psr\Clock\ClockInterface;
use TradeManeuver\Trading\Application\IntegrationEvent\SignalOutcomeIntegrationEvent;
use TradeManeuver\Trading\Application\Port\IntegrationEventPublisherInterface;
use TradeManeuver\Trading\Infrastructure\Messaging\Jobs\PublishSignalOutcomeJob;

/**
 * Writes the message to the transactional outbox on the connection of the use-case transaction, and queues the job
 * that publishes it. The job goes to Laravel's PostgreSQL queue on the same connection, so the entry and its job
 * commit or roll back together.
 */
final readonly class OutboxIntegrationEventPublisher implements IntegrationEventPublisherInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private Dispatcher $dispatcher,
    ) {}

    public function publish(SignalOutcomeIntegrationEvent $event): void
    {
        $id = $this->connection->table('trading_outbox_messages')->insertGetId([
            'message_id' => $event->id,
            'message_type' => SignalOutcomeIntegrationEvent::TYPE,
            'recipient' => $event->recipient,
            'payload' => json_encode($event->payload(), JSON_THROW_ON_ERROR),
            'recorded_at' => $this->clock->now()->format('Y-m-d H:i:s.uP'),
        ]);
        $this->dispatcher->dispatch(new PublishSignalOutcomeJob($id));
    }
}
