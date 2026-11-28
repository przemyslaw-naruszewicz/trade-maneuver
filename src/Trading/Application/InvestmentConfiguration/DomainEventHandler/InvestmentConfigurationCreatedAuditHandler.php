<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\InvestmentConfiguration\DomainEventHandler;

use Psr\Clock\ClockInterface;
use TradeManeuver\Trading\Application\Audit\AuditRecordDto;
use TradeManeuver\Trading\Application\DomainEvent\DomainEventContext;
use TradeManeuver\Trading\Application\Port\AuditTrailInterface;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Event\InvestmentConfigurationCreated;

/**
 * Audits the creation of an investment configuration with all its settings. The event carries no time, so the
 * audit record takes the time of the creation's transaction.
 */
final readonly class InvestmentConfigurationCreatedAuditHandler
{
    public function __construct(
        private AuditTrailInterface $auditTrail,
        private ClockInterface $clock,
    ) {}

    public function handle(InvestmentConfigurationCreated $event, DomainEventContext $context): void
    {
        $this->auditTrail->record(new AuditRecordDto($context->actor, $this->clock->now(), 'investment_configuration', $event->id->toString(), 'created', [
            ...$context->auditValues,
            'reference' => $event->reference->toString(),
            'currency' => $event->currency->code(),
            'brokerageAccount' => $event->brokerageAccount->identifier(),
            'orderAmount' => $event->orderAmount->amount(),
            'limitPriceTolerance' => $event->limitPriceTolerance->toString(),
            'authorizedAnalyticsApplications' => $event->authorizedAnalyticsApplications->identifiers(),
        ]));
    }
}
