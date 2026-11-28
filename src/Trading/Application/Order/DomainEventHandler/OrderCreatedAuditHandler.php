<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Order\DomainEventHandler;

use Psr\Log\LoggerInterface;
use TradeManeuver\Trading\Application\Audit\AuditContext;
use TradeManeuver\Trading\Application\Audit\AuditRecordDto;
use TradeManeuver\Trading\Application\Port\AuditTrailInterface;
use TradeManeuver\Trading\Domain\Order\Event\OrderCreated;

/**
 * Audits and logs the creation of an order with its settings snapshot.
 */
final readonly class OrderCreatedAuditHandler
{
    public function __construct(
        private AuditTrailInterface $auditTrail,
        private LoggerInterface $logger,
    ) {}

    public function handle(OrderCreated $event, AuditContext $context): void
    {
        $orderId = $event->id->toString();
        $this->auditTrail->record(new AuditRecordDto($context->actor, $event->createdAt, 'order', $orderId, 'created', [
            'tradeSignalId' => $event->tradeSignalId->toString(),
            'investmentConfigurationId' => $event->investmentConfigurationId->toString(),
            'status' => $event->status->value,
            'direction' => $event->direction->value,
            'instrumentSymbol' => $event->instrument->symbol(),
            'instrumentMic' => $event->instrument->mic()->code(),
            'limitPrice' => $event->limitPrice->amount(),
            'quantity' => (string) $event->quantity->units(),
            'orderAmount' => $event->settings->orderAmount->amount(),
            'limitPriceTolerance' => $event->settings->limitPriceTolerance->toString(),
            'currency' => $event->settings->currency->code(),
            'brokerageAccount' => $event->settings->brokerageAccount->identifier(),
        ]));
        $this->logger->info('An order awaiting approval is created.', ['signalId' => $event->tradeSignalId->toString(), 'orderId' => $orderId]);
    }
}
