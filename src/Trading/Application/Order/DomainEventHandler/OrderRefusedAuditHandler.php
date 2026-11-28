<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Order\DomainEventHandler;

use Psr\Log\LoggerInterface;
use TradeManeuver\Trading\Application\Audit\AuditContext;
use TradeManeuver\Trading\Application\Audit\AuditRecordDto;
use TradeManeuver\Trading\Application\Port\AuditTrailInterface;
use TradeManeuver\Trading\Domain\Order\Event\OrderRefused;

/**
 * Audits and logs the operator's refusal of an order. The audit record alone keeps the operator's note, which the
 * context carries.
 */
final readonly class OrderRefusedAuditHandler
{
    public function __construct(
        private AuditTrailInterface $auditTrail,
        private LoggerInterface $logger,
    ) {}

    public function handle(OrderRefused $event, AuditContext $context): void
    {
        $this->auditTrail->record(new AuditRecordDto($context->actor, $event->refusedAt, 'order', $event->id->toString(), 'refused', [
            ...$context->auditValues,
            'previousLimitPrice' => $event->limitPrice->amount(),
            'limitPrice' => $event->limitPrice->amount(),
            'currency' => $event->limitPrice->currencyCode(),
            'previousQuantity' => (string) $event->quantity->units(),
            'quantity' => (string) $event->quantity->units(),
        ]));
        $this->logger->info('The operator refused an order.', ['orderId' => $event->id->toString(), 'signalId' => $event->tradeSignalId->toString()]);
    }
}
