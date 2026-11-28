<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\TradeSignal\DomainEventHandler;

use Psr\Log\LoggerInterface;
use TradeManeuver\Trading\Application\Audit\AuditContext;
use TradeManeuver\Trading\Application\Audit\AuditRecordDto;
use TradeManeuver\Trading\Application\IntegrationEvent\SignalOutcomeIntegrationEvent;
use TradeManeuver\Trading\Application\Port\AuditTrailInterface;
use TradeManeuver\Trading\Application\Port\IntegrationEventPublisherInterface;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalDeclined;

/**
 * Records the signal outcome of a decline: its audit record, its outbox entry for the analytics application that
 * sent the trade signal, and a log entry.
 */
final readonly class TradeSignalDeclinedSignalOutcomeHandler
{
    public function __construct(
        private AuditTrailInterface $auditTrail,
        private IntegrationEventPublisherInterface $integrationEventPublisher,
        private LoggerInterface $logger,
    ) {}

    public function handle(TradeSignalDeclined $event, AuditContext $context): void
    {
        $signalId = $event->id->toString();
        $outcomeId = $event->outcome->id->toString();
        $reason = $event->reason->value;
        $this->auditTrail->record(new AuditRecordDto($context->actor, $event->outcome->occurredAt, 'trade_signal', $signalId, 'declined', [
            'outcomeId' => $outcomeId,
            'reason' => $reason,
        ]));
        $this->integrationEventPublisher->publish(SignalOutcomeIntegrationEvent::of($event->id, $event->outcome, $event->source));
        $this->logger->info('A trade signal is declined.', ['signalId' => $signalId, 'outcomeId' => $outcomeId, 'reason' => $reason]);
    }
}
