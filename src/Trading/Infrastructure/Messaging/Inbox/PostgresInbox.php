<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\Inbox;

use Illuminate\Database\ConnectionInterface;
use TradeManeuver\Trading\Application\Inbox\InboxDelivery;
use TradeManeuver\Trading\Application\Inbox\InboxEntryDto;
use TradeManeuver\Trading\Application\Port\InboxInterface;

/**
 * The primary key on consumer and message id decides which delivery is first. `ON CONFLICT DO NOTHING` makes a
 * concurrent delivery wait for the first one's transaction and then see its committed entry, instead of failing.
 */
final readonly class PostgresInbox implements InboxInterface
{
    private const string TABLE = 'trading_inbox_entries';

    public function __construct(private ConnectionInterface $connection) {}

    public function register(InboxEntryDto $entry): InboxDelivery
    {
        $inserted = $this->connection->table(self::TABLE)->insertOrIgnore([
            'consumer' => $entry->consumer,
            'message_id' => $entry->messageId,
            'fingerprint' => $entry->fingerprint,
            'received_at' => $entry->receivedAt->format('Y-m-d H:i:s.uP'),
        ]);
        if ($inserted === 1) {
            return InboxDelivery::First;
        }

        $storedFingerprint = $this->connection->table(self::TABLE)
            ->where('consumer', $entry->consumer)
            ->where('message_id', $entry->messageId)
            ->value('fingerprint');

        return $storedFingerprint === $entry->fingerprint ? InboxDelivery::Duplicate : InboxDelivery::ConflictingDuplicate;
    }
}
