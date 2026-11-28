<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Order\DomainEventHandler;

use Psr\Log\LoggerInterface;
use TradeManeuver\Trading\Application\Audit\AuditRecordDto;
use TradeManeuver\Trading\Application\DomainEvent\DomainEventContext;
use TradeManeuver\Trading\Application\Port\AuditTrailInterface;
use TradeManeuver\Trading\Domain\Order\Event\OrderApproved;

/**
 * Audits and logs the operator's approval of an order, with the limit price and the quantity before and after it.
 */
final readonly class OrderApprovedAuditHandler
{
    public function __construct(
        private AuditTrailInterface $auditTrail,
        private LoggerInterface $logger,
    ) {}

    public function handle(OrderApproved $event, DomainEventContext $context): void
    {
        $this->auditTrail->record(new AuditRecordDto($context->actor, $event->approvedAt, 'order', $event->id->toString(), 'approved', [
            'previousLimitPrice' => $event->previousLimitPrice->amount(),
            'limitPrice' => $event->limitPrice->amount(),
            'currency' => $event->limitPrice->currencyCode(),
            'previousQuantity' => (string) $event->previousQuantity->units(),
            'quantity' => (string) $event->quantity->units(),
        ]));
        $this->logger->info('The operator approved an order.', ['orderId' => $event->id->toString(), 'signalId' => $event->tradeSignalId->toString()]);
    }
}
