<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\TradeSignal\DomainEventHandler;

use TradeManeuver\Trading\Application\Audit\AuditRecordDto;
use TradeManeuver\Trading\Application\DomainEvent\DomainEventContext;
use TradeManeuver\Trading\Application\Port\AuditTrailInterface;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalReceived;

/**
 * Audits the reception of a trade signal with the message metadata that the context carries.
 */
final readonly class TradeSignalReceivedAuditHandler
{
    public function __construct(
        private AuditTrailInterface $auditTrail,
    ) {}

    public function handle(TradeSignalReceived $event, DomainEventContext $context): void
    {
        $this->auditTrail->record(new AuditRecordDto($context->actor, $event->receivedAt, 'trade_signal', $event->id->toString(), 'received', $context->auditValues));
    }
}
