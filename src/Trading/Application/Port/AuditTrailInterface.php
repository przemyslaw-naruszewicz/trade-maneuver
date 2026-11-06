<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Port;

use TradeManeuver\Trading\Application\Audit\AuditRecordDto;

/**
 * An append-only audit trail written in the caller's transaction.
 */
interface AuditTrailInterface
{
    public function record(AuditRecordDto $record): void;
}
