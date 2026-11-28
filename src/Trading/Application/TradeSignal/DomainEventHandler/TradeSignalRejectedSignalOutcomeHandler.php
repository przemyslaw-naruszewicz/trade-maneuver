<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\TradeSignal\DomainEventHandler;

use Psr\Log\LoggerInterface;
use TradeManeuver\Trading\Application\Audit\AuditRecordDto;
use TradeManeuver\Trading\Application\DomainEvent\DomainEventContext;
use TradeManeuver\Trading\Application\IntegrationEvent\SignalOutcomeIntegrationEvent;
use TradeManeuver\Trading\Application\Port\AuditTrailInterface;
use TradeManeuver\Trading\Application\Port\IntegrationEventPublisherInterface;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalRejected;

/**
 * Records the signal outcome of a rejection, at intake or in evaluation: its audit record, its outbox entry for the
 * analytics application that sent the trade signal, and a log entry.
 */
final readonly class TradeSignalRejectedSignalOutcomeHandler
{
    public function __construct(
        private AuditTrailInterface $auditTrail,
        private IntegrationEventPublisherInterface $integrationEventPublisher,
        private LoggerInterface $logger,
    ) {}

    public function handle(TradeSignalRejected $event, DomainEventContext $context): void
    {
        $signalId = $event->id->toString();
        $outcomeId = $event->outcome->id->toString();
        $violations = $event->violations->descriptions();
        $this->auditTrail->record(new AuditRecordDto($context->actor, $event->outcome->occurredAt, 'trade_signal', $signalId, 'rejected', [
            ...$context->auditValues,
            'outcomeId' => $outcomeId,
            'violations' => $violations,
        ]));
        $this->integrationEventPublisher->publish(SignalOutcomeIntegrationEvent::of($event->id, $event->outcome, $event->source));
        $this->logger->info('A trade signal is rejected.', ['signalId' => $signalId, 'outcomeId' => $outcomeId, 'violations' => $violations]);
    }
}
