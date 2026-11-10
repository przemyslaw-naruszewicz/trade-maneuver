<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Workflow\TradeSignalEvaluation;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use TradeManeuver\Trading\Application\Audit\AuditRecordDto;
use TradeManeuver\Trading\Application\IntegrationEvent\SignalOutcomeIntegrationEvent;
use TradeManeuver\Trading\Application\Port\AuditTrailInterface;
use TradeManeuver\Trading\Application\Port\IntegrationEventPublisherInterface;
use TradeManeuver\Trading\Application\Port\TransactionManagerInterface;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalDeclined;
use TradeManeuver\Trading\Domain\TradeSignal\Repository\TradeSignalRepositoryInterface;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

/**
 * Evaluates an accepted trade signal after its intake commits, one step per transaction. The first step checks signal
 * validity and direction, which need no broker; a trade signal that passes it waits for the instrument checks. A
 * signal outcome commits with its audit record and its outbox entry. The trade signal stays locked during a step, so
 * a job that runs twice, even concurrently, reaches one signal outcome.
 */
final readonly class TradeSignalEvaluationOrchestrator
{
    private const string ACTOR = 'trade-maneuver';

    private const string ENTITY_TYPE = 'trade_signal';

    public function __construct(
        private TradeSignalRepositoryInterface $tradeSignals,
        private IntegrationEventPublisherInterface $integrationEventPublisher,
        private AuditTrailInterface $auditTrail,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private TransactionManagerInterface $transactionManager,
    ) {}

    public function evaluate(TradeSignalId $id): void
    {
        $this->transactionManager->transactional(fn () => $this->checkValidityAndDirection($id));
    }

    private function checkValidityAndDirection(TradeSignalId $id): void
    {
        $tradeSignal = $this->tradeSignals->getLocked($id);
        if (! $tradeSignal->awaitsEvaluation()) {
            // The job ran before, or the trade signal was rejected at intake.
            $this->logger->info('A trade signal already has a signal outcome, so it is not evaluated again.', ['signalId' => $id->toString()]);

            return;
        }

        $tradeSignal->evaluateValidityAndDirection($this->tradeSignals->nextOutcomeIdentity(), $this->clock->now());
        $this->tradeSignals->save($tradeSignal);

        foreach ($tradeSignal->releaseEvents() as $event) {
            // Checking validity and direction records only declines; recordDecline() refuses any other event.
            /** @var TradeSignalDeclined $event */
            $this->recordDecline($event);
        }
    }

    private function recordDecline(TradeSignalDeclined $event): void
    {
        $outcomeId = $event->outcome->id->toString();
        $reason = $event->reason->value;
        $this->auditTrail->record(new AuditRecordDto(
            self::ACTOR,
            $event->outcome->occurredAt,
            self::ENTITY_TYPE,
            $event->id->toString(),
            'declined',
            ['outcomeId' => $outcomeId, 'reason' => $reason],
        ));
        $this->integrationEventPublisher->publish(SignalOutcomeIntegrationEvent::of($event->id, $event->outcome, $event->source));
        $this->logger->info('A trade signal is declined.', ['signalId' => $event->id->toString(), 'outcomeId' => $outcomeId, 'reason' => $reason]);
    }
}
