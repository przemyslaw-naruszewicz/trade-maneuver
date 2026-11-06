<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Audit;

use DateTimeImmutable;

/**
 * One append-only entry of the audit trail. Values hold exact decimal strings, never floats.
 */
final readonly class AuditRecordDto
{
    /**
     * @param  array<string, string|list<string>>  $values
     */
    public function __construct(
        public string $actor,
        public DateTimeImmutable $occurredAt,
        public string $entityType,
        public string $entityId,
        public string $action,
        public array $values,
    ) {}
}
