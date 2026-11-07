<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use TradeManeuver\Trading\Application\Audit\AuditRecordDto;
use TradeManeuver\Trading\Application\Inbox\InboxDelivery;
use TradeManeuver\Trading\Application\Inbox\InboxEntryDto;
use TradeManeuver\Trading\Application\IntegrationEvent\SignalOutcomeIntegrationEvent;
use TradeManeuver\Trading\Application\Port\AnalyticsApplicationRegistryInterface;
use TradeManeuver\Trading\Application\Port\AuditTrailInterface;
use TradeManeuver\Trading\Application\Port\InboxInterface;
use TradeManeuver\Trading\Application\Port\IntegrationEventPublisherInterface;
use TradeManeuver\Trading\Application\Port\TransactionManagerInterface;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository\InvestmentConfigurationRepositoryInterface;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalReceived;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalRejected;
use TradeManeuver\Trading\Domain\TradeSignal\Repository\TradeSignalRepositoryInterface;
use TradeManeuver\Trading\Domain\TradeSignal\TradeSignal;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ContractViolation;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ContractViolations;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcome;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalContent;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

/**
 * The intake of trade signals at the application boundary. Each delivery commits its inbox entry, trade signal,
 * audit records and signal outcome in one transaction, so a redelivery never repeats processing.
 */
final readonly class ReceiveTradeSignalCommandHandler
{
    public const string CONSUMER = 'trading.trade-signal-intake';

    private const string ENTITY_TYPE = 'trade_signal';

    public function __construct(
        private TradeSignalRepositoryInterface $tradeSignals,
        private InvestmentConfigurationRepositoryInterface $investmentConfigurations,
        private InboxInterface $inbox,
        private IntegrationEventPublisherInterface $integrationEventPublisher,
        private AuditTrailInterface $auditTrail,
        private AnalyticsApplicationRegistryInterface $analyticsApplicationRegistry,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private TransactionManagerInterface $transactionManager,
    ) {}

    public function handle(ReceiveTradeSignalCommand $command): TradeSignalIntakeResult
    {
        $message = TradeSignalMessage::fromBody($command->body);
        $receivedAt = $this->clock->now();

        $id = $message->id();
        if ($id === null) {
            return $this->setAside($message, $command, $receivedAt, 'The message has no valid id.');
        }
        $source = $message->source();
        if ($source === null || ! $this->analyticsApplicationRegistry->isKnown($source)) {
            return $this->setAside($message, $command, $receivedAt, 'The source is not a known analytics application.');
        }
        $publisherIdentity = $command->publisherIdentity;
        if ($publisherIdentity === null || ! $this->analyticsApplicationRegistry->publishesAs($source, $publisherIdentity)) {
            return $this->setAside($message, $command, $receivedAt, 'The publisher identity is not the one registered for the source.');
        }

        $delivery = $this->transactionManager->transactional(
            fn (): InboxDelivery => $this->receive($id, $source, $message, $receivedAt),
        );
        if ($delivery === InboxDelivery::ConflictingDuplicate) {
            $this->logger->warning('A trade signal message reuses an id with different content and is set aside.', $message->metadata());

            return TradeSignalIntakeResult::NotProcessable;
        }

        return TradeSignalIntakeResult::Accepted;
    }

    private function receive(TradeSignalId $id, AnalyticsApplicationId $source, TradeSignalMessage $message, DateTimeImmutable $receivedAt): InboxDelivery
    {
        $delivery = $this->inbox->register(new InboxEntryDto(self::CONSUMER, $id->toString(), $message->fingerprint(), $receivedAt));
        match ($delivery) {
            InboxDelivery::First => $this->process($id, $source, $message, $receivedAt),
            InboxDelivery::Duplicate => $this->redeliver($id, $source, $message, $receivedAt),
            InboxDelivery::ConflictingDuplicate => $this->recordAudit($source, $receivedAt, $id->toString(), 'conflicting_duplicate', $message->metadata()),
        };

        return $delivery;
    }

    private function process(TradeSignalId $id, AnalyticsApplicationId $source, TradeSignalMessage $message, DateTimeImmutable $receivedAt): void
    {
        $tradeSignal = $this->tradeSignalOf($id, $source, $message, $receivedAt);
        $this->tradeSignals->add($tradeSignal);

        foreach ($tradeSignal->releaseEvents() as $event) {
            if ($event instanceof TradeSignalReceived) {
                $this->recordAudit($source, $receivedAt, $id->toString(), 'received', $message->metadata());
            } else {
                $this->recordRejection($event, $message, $receivedAt);
            }
        }
    }

    private function tradeSignalOf(TradeSignalId $id, AnalyticsApplicationId $source, TradeSignalMessage $message, DateTimeImmutable $receivedAt): TradeSignal
    {
        $content = (new TradeSignalContentTranslator)->translate($message->fields() ?? []);
        $violations = $content instanceof TradeSignalContent ? $this->authorizationViolations($content, $source) : $content;
        if ($violations !== null) {
            return TradeSignal::reject($id, $source, $violations, $this->tradeSignals->nextOutcomeIdentity(), $receivedAt);
        }

        return TradeSignal::receive($id, $source, $content, $receivedAt);
    }

    private function authorizationViolations(TradeSignalContent $content, AnalyticsApplicationId $source): ?ContractViolations
    {
        $investmentConfiguration = $this->investmentConfigurations->findByReference($content->investmentConfigurationRef);
        if ($investmentConfiguration === null) {
            return ContractViolations::of(new ContractViolation('investmentConfigurationRef', 'The investment configuration is unknown.'));
        }
        if (! $investmentConfiguration->authorizes($source)) {
            return ContractViolations::of(new ContractViolation('source', 'The analytics application is not authorized for the investment configuration.'));
        }

        return null;
    }

    private function recordRejection(TradeSignalRejected $event, TradeSignalMessage $message, DateTimeImmutable $receivedAt): void
    {
        $this->integrationEventPublisher->publish(SignalOutcomeIntegrationEvent::of($event->id, $event->outcome, $event->source));
        $this->recordAudit($event->source, $receivedAt, $event->id->toString(), 'rejected', [
            ...$message->metadata(),
            'outcomeId' => $event->outcome->id->toString(),
            'violations' => self::describe($event->outcome),
        ]);
    }

    private function redeliver(TradeSignalId $id, AnalyticsApplicationId $source, TradeSignalMessage $message, DateTimeImmutable $receivedAt): void
    {
        $outcome = $this->tradeSignals->get($id)->outcome();
        if ($outcome !== null) {
            $this->integrationEventPublisher->publish(SignalOutcomeIntegrationEvent::of($id, $outcome, $source));
        }
        $this->recordAudit($source, $receivedAt, $id->toString(), 'duplicate_delivered', [
            ...$message->metadata(),
            'outcomeId' => $outcome?->id->toString() ?? '',
        ]);
    }

    private function setAside(TradeSignalMessage $message, ReceiveTradeSignalCommand $command, DateTimeImmutable $receivedAt, string $reason): TradeSignalIntakeResult
    {
        $metadata = [
            ...$message->metadata(),
            'publisherIdentity' => $command->publisherIdentity ?? '',
            'reason' => $reason,
        ];
        $this->transactionManager->transactional(function () use ($command, $receivedAt, $metadata): void {
            $this->auditTrail->record(new AuditRecordDto(
                'publisher:'.($command->publisherIdentity ?? ''),
                $receivedAt,
                self::ENTITY_TYPE,
                $metadata['id'],
                'set_aside',
                $metadata,
            ));
        });
        $this->logger->warning('A trade signal message cannot be processed and is set aside.', $metadata);

        return TradeSignalIntakeResult::NotProcessable;
    }

    /**
     * @param  array<string, string|list<string>>  $values
     */
    private function recordAudit(AnalyticsApplicationId $source, DateTimeImmutable $occurredAt, string $entityId, string $action, array $values): void
    {
        $this->auditTrail->record(new AuditRecordDto(
            'analytics-application:'.$source->toString(),
            $occurredAt,
            self::ENTITY_TYPE,
            $entityId,
            $action,
            $values,
        ));
    }

    /**
     * @return list<string>
     */
    private static function describe(SignalOutcome $outcome): array
    {
        $violations = [];
        foreach ($outcome->violations->all() as $violation) {
            $violations[] = $violation->field.': '.$violation->message;
        }

        return $violations;
    }
}
