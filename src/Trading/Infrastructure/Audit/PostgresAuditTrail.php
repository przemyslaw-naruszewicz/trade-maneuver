<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Audit;

use Illuminate\Database\ConnectionInterface;
use TradeManeuver\Trading\Application\Audit\AuditRecordDto;
use TradeManeuver\Trading\Application\Port\AuditTrailInterface;

final readonly class PostgresAuditTrail implements AuditTrailInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function record(AuditRecordDto $record): void
    {
        $this->connection->table('trading_audit_records')->insert([
            'actor' => $record->actor,
            'occurred_at' => $record->occurredAt->format('Y-m-d H:i:s.uP'),
            'entity_type' => $record->entityType,
            'entity_id' => $record->entityId,
            'action' => $record->action,
            'values' => json_encode($record->values, JSON_THROW_ON_ERROR),
        ]);
    }
}
