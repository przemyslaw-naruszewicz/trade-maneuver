<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\Outbox;

use Illuminate\Database\ConnectionInterface;
use Psr\Clock\ClockInterface;

final readonly class OutboxDispatcher
{
    public function __construct(
        private ConnectionInterface $connection,
        private SignalOutcomePublisherInterface $publisher,
        private ClockInterface $clock,
    ) {}

    public function dispatchDue(): int
    {
        return $this->connection->table('trading_outbox_messages')->whereNull('sent_at')
            ->update(['sent_at' => $this->clock->now()->format('Y-m-d H:i:s.uP')]);
    }
}
