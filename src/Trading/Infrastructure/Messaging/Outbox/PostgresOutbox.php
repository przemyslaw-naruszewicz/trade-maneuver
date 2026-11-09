<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\Outbox;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * The publishing side of the transactional outbox: it reads an unsent entry and records that it was sent.
 */
final readonly class PostgresOutbox
{
    private const string TABLE = 'trading_outbox_messages';

    public function __construct(private ConnectionInterface $connection) {}

    public function findUnsent(int $id): ?OutboxMessageDto
    {
        $row = $this->connection->table(self::TABLE)
            ->select(['id', 'message_id', 'message_type', 'recipient', 'payload'])
            ->selectRaw("payload->>'signalId' AS signal_id")
            ->where('id', $id)
            ->whereNull('sent_at')
            ->first();

        return $row === null ? null : new OutboxMessageDto(
            (int) $row->id,
            (string) $row->message_id,
            (string) $row->message_type,
            (string) $row->recipient,
            (string) $row->payload,
            (string) $row->signal_id,
        );
    }

    public function markSent(OutboxMessageDto $message, DateTimeImmutable $sentAt): void
    {
        $this->connection->table(self::TABLE)
            ->where('id', $message->id)
            ->update(['sent_at' => $sentAt->format('Y-m-d H:i:s.uP')]);
    }
}
