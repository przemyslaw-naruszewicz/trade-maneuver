<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\Outbox;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * The dispatcher's view of the transactional outbox: the unsent entries that are due, and what became of each attempt.
 * Each change commits on its own, outside any use-case transaction.
 */
final readonly class PostgresOutbox
{
    private const string TABLE = 'trading_outbox_messages';

    private const string TIMESTAMP_FORMAT = 'Y-m-d H:i:s.uP';

    public function __construct(private ConnectionInterface $connection) {}

    /**
     * The oldest unsent entries whose next attempt is due, in the order they were recorded.
     *
     * @return list<OutboxMessageDto>
     */
    public function due(DateTimeImmutable $now, int $limit): array
    {
        $rows = $this->connection->table(self::TABLE)
            ->whereNull('sent_at')
            ->where(static function ($query) use ($now): void {
                $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now->format(self::TIMESTAMP_FORMAT));
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $messages = [];
        foreach ($rows as $row) {
            $messages[] = new OutboxMessageDto(
                (int) $row->id,
                (string) $row->message_id,
                (string) $row->message_type,
                (string) $row->recipient,
                (string) $row->payload,
                (int) $row->attempts,
            );
        }

        return $messages;
    }

    public function markSent(OutboxMessageDto $message, DateTimeImmutable $sentAt): void
    {
        $this->connection->table(self::TABLE)
            ->where('id', $message->id)
            ->update(['sent_at' => $sentAt->format(self::TIMESTAMP_FORMAT)]);
    }

    public function recordFailedAttempt(OutboxMessageDto $message, string $error, DateTimeImmutable $nextAttemptAt): void
    {
        $this->connection->table(self::TABLE)
            ->where('id', $message->id)
            ->update([
                'attempts' => $message->attempts + 1,
                'next_attempt_at' => $nextAttemptAt->format(self::TIMESTAMP_FORMAT),
                'last_error' => $error,
            ]);
    }
}
