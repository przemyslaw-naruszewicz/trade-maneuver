<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\TradeSignal\SignalOutcome;

use Psr\Log\LoggerInterface;
use TradeManeuver\Trading\Application\Audit\AuditRecordDto;
use TradeManeuver\Trading\Application\IntegrationEvent\SignalOutcomeIntegrationEvent;
use TradeManeuver\Trading\Application\Port\AuditTrailInterface;
use TradeManeuver\Trading\Application\Port\IntegrationEventPublisherInterface;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalDeclined;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalRejected;

/**
 * Records a trade signal's signal outcome in the caller's transaction: its audit record, its outbox entry for the
 * analytics application that sent the trade signal, and a log entry.
 */
final readonly class SignalOutcomeRecorder
{
    private const string ACTOR = 'trade-maneuver';

    private const string ENTITY_TYPE = 'trade_signal';

    public function __construct(
        private IntegrationEventPublisherInterface $integrationEventPublisher,
        private AuditTrailInterface $auditTrail,
        private LoggerInterface $logger,
    ) {}

    public function record(TradeSignalDeclined|TradeSignalRejected $event): void
    {
        $outcomeId = $event->outcome->id->toString();
        if ($event instanceof TradeSignalDeclined) {
            $reason = $event->reason->value;
            $this->recordAudit($event, 'declined', ['outcomeId' => $outcomeId, 'reason' => $reason]);
            $this->publish($event);
            $this->logger->info('A trade signal is declined.', ['signalId' => $event->id->toString(), 'outcomeId' => $outcomeId, 'reason' => $reason]);

            return;
        }

        $violations = $event->violations->descriptions();
        $this->recordAudit($event, 'rejected', ['outcomeId' => $outcomeId, 'violations' => $violations]);
        $this->publish($event);
        $this->logger->info('A trade signal is rejected.', ['signalId' => $event->id->toString(), 'outcomeId' => $outcomeId, 'violations' => $violations]);
    }

    private function publish(TradeSignalDeclined|TradeSignalRejected $event): void
    {
        $this->integrationEventPublisher->publish(SignalOutcomeIntegrationEvent::of($event->id, $event->outcome, $event->source));
    }

    /**
     * @param  array<string, string|list<string>>  $values
     */
    private function recordAudit(TradeSignalDeclined|TradeSignalRejected $event, string $action, array $values): void
    {
        $this->auditTrail->record(new AuditRecordDto(self::ACTOR, $event->outcome->occurredAt, self::ENTITY_TYPE, $event->id->toString(), $action, $values));
    }
}
