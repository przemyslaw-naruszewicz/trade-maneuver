<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Workflow\TradeSignalEvaluation;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use TradeManeuver\Trading\Application\Audit\AuditRecordDto;
use TradeManeuver\Trading\Application\IntegrationEvent\SignalOutcomeIntegrationEvent;
use TradeManeuver\Trading\Application\Port\AuditTrailInterface;
use TradeManeuver\Trading\Application\Port\BrokerGatewayInterface;
use TradeManeuver\Trading\Application\Port\Exception\BrokerUnavailableException;
use TradeManeuver\Trading\Application\Port\IntegrationEventPublisherInterface;
use TradeManeuver\Trading\Application\Port\TransactionManagerInterface;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository\Exception\InvestmentConfigurationDoesNotExistException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository\InvestmentConfigurationRepositoryInterface;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalDeclined;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalRejected;
use TradeManeuver\Trading\Domain\TradeSignal\Repository\TradeSignalRepositoryInterface;
use TradeManeuver\Trading\Domain\TradeSignal\TradeSignal;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Instrument;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\InstrumentListing;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

/**
 * Evaluates an accepted trade signal after its intake commits, one step per transaction: first signal validity and
 * direction, then the instrument checks against the broker. Each step that a trade signal passes is recorded in the
 * step's transaction, so a rerun continues with the first step not yet passed. A signal outcome commits with its audit
 * record and its outbox entry. The trade signal stays locked during a step, so a job that runs twice, even
 * concurrently, reaches one signal outcome. A trade signal that passes every step awaits order creation.
 */
final readonly class TradeSignalEvaluationOrchestrator
{
    private const string ACTOR = 'trade-maneuver';

    private const string ENTITY_TYPE = 'trade_signal';

    public function __construct(
        private TradeSignalRepositoryInterface $tradeSignals,
        private InvestmentConfigurationRepositoryInterface $investmentConfigurations,
        private TradeSignalEvaluationStoreInterface $evaluations,
        private BrokerGatewayInterface $brokerGateway,
        private IntegrationEventPublisherInterface $integrationEventPublisher,
        private AuditTrailInterface $auditTrail,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private TransactionManagerInterface $transactionManager,
    ) {}

    /**
     * @throws BrokerUnavailableException when the instrument lookup fails; the job runs again later
     */
    public function evaluate(TradeSignalId $id): void
    {
        $instrument = $this->transactionManager->transactional(fn (): ?Instrument => $this->checkValidityAndDirection($id));
        if ($instrument === null) {
            return;
        }

        // The lookup runs between the transactions, so no lock is held while the broker answers.
        $listing = $this->brokerGateway->lookUpInstrument($instrument);
        $this->transactionManager->transactional(fn () => $this->checkInstrument($id, $listing));
    }

    /**
     * @return Instrument|null the instrument to look up, or null when the instrument checks do not run
     */
    private function checkValidityAndDirection(TradeSignalId $id): ?Instrument
    {
        $tradeSignal = $this->tradeSignals->getLocked($id);
        if (! $tradeSignal->awaitsEvaluation()) {
            // The job ran before, or the trade signal was rejected at intake.
            $this->logger->info('A trade signal already has a signal outcome, so it is not evaluated again.', ['signalId' => $id->toString()]);

            return null;
        }

        $progress = $this->evaluations->progressOf($id);
        if (! $progress->hasPassed(EvaluationStep::ValidityAndDirection)) {
            $tradeSignal->evaluateValidityAndDirection($this->tradeSignals->nextOutcomeIdentity(), $this->clock->now());
            $this->complete($tradeSignal, $progress, EvaluationStep::ValidityAndDirection);
        }

        if (! $tradeSignal->awaitsEvaluation() || $progress->hasPassed(EvaluationStep::InstrumentChecks)) {
            return null;
        }

        return $tradeSignal->instrument();
    }

    private function checkInstrument(TradeSignalId $id, ?InstrumentListing $listing): void
    {
        $tradeSignal = $this->tradeSignals->getLocked($id);
        $progress = $this->evaluations->progressOf($id);
        if (! $tradeSignal->awaitsEvaluation() || $progress->hasPassed(EvaluationStep::InstrumentChecks)) {
            // A concurrent run of the job finished this step first.
            return;
        }

        $reference = $tradeSignal->investmentConfigurationRef();
        $investmentConfiguration = $this->investmentConfigurations->findByReference($reference)
            ?? throw InvestmentConfigurationDoesNotExistException::withReference($reference);
        $tradeSignal->checkInstrument($listing, $investmentConfiguration->currency(), $this->tradeSignals->nextOutcomeIdentity(), $this->clock->now());
        $this->complete($tradeSignal, $progress, EvaluationStep::InstrumentChecks);
        if ($tradeSignal->awaitsEvaluation()) {
            $this->logger->info('A trade signal passed the instrument checks and awaits order creation.', ['signalId' => $id->toString()]);
        }
    }

    /**
     * Saves the trade signal after a step and records either its signal outcome or the passed step.
     */
    private function complete(TradeSignal $tradeSignal, TradeSignalEvaluationProgress $progress, EvaluationStep $step): void
    {
        $this->tradeSignals->save($tradeSignal);
        foreach ($tradeSignal->releaseEvents() as $event) {
            match (true) {
                $event instanceof TradeSignalDeclined => $this->recordDecline($event),
                $event instanceof TradeSignalRejected => $this->recordRejection($event),
                default => null,
            };
        }

        if ($tradeSignal->awaitsEvaluation()) {
            $progress->pass($step, $this->clock->now());
            $this->evaluations->save($progress);
        }
    }

    private function recordDecline(TradeSignalDeclined $event): void
    {
        $outcomeId = $event->outcome->id->toString();
        $reason = $event->reason->value;
        $this->recordAudit($event->outcome->occurredAt, $event->id, 'declined', ['outcomeId' => $outcomeId, 'reason' => $reason]);
        $this->integrationEventPublisher->publish(SignalOutcomeIntegrationEvent::of($event->id, $event->outcome, $event->source));
        $this->logger->info('A trade signal is declined.', ['signalId' => $event->id->toString(), 'outcomeId' => $outcomeId, 'reason' => $reason]);
    }

    private function recordRejection(TradeSignalRejected $event): void
    {
        $outcomeId = $event->outcome->id->toString();
        $violations = [];
        foreach ($event->violations->all() as $violation) {
            $violations[] = $violation->field.': '.$violation->message;
        }
        $this->recordAudit($event->outcome->occurredAt, $event->id, 'rejected', ['outcomeId' => $outcomeId, 'violations' => $violations]);
        $this->integrationEventPublisher->publish(SignalOutcomeIntegrationEvent::of($event->id, $event->outcome, $event->source));
        $this->logger->info('A trade signal is rejected.', ['signalId' => $event->id->toString(), 'outcomeId' => $outcomeId, 'violations' => $violations]);
    }

    /**
     * @param  array<string, string|list<string>>  $values
     */
    private function recordAudit(DateTimeImmutable $occurredAt, TradeSignalId $id, string $action, array $values): void
    {
        $this->auditTrail->record(new AuditRecordDto(self::ACTOR, $occurredAt, self::ENTITY_TYPE, $id->toString(), $action, $values));
    }
}
