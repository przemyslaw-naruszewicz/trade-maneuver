<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Order\Command\RefuseOrder;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use TradeManeuver\Trading\Application\Audit\AuditRecordDto;
use TradeManeuver\Trading\Application\Order\Exception\OrderAlreadyDecidedException;
use TradeManeuver\Trading\Application\Order\Exception\UnknownOrderException;
use TradeManeuver\Trading\Application\Port\AuditTrailInterface;
use TradeManeuver\Trading\Application\Port\SignalOutcomeRecordingQueueInterface;
use TradeManeuver\Trading\Application\Port\TransactionManagerInterface;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorId;
use TradeManeuver\Trading\Domain\Order\Event\OrderRefused;
use TradeManeuver\Trading\Domain\Order\Exception\InvalidOrderValueException;
use TradeManeuver\Trading\Domain\Order\Exception\OrderNotAwaitingApprovalException;
use TradeManeuver\Trading\Domain\Order\Repository\Exception\OrderChangedConcurrentlyException;
use TradeManeuver\Trading\Domain\Order\Repository\Exception\OrderDoesNotExistException;
use TradeManeuver\Trading\Domain\Order\Repository\OrderRepositoryInterface;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;

/**
 * Refuses an order in one transaction with its audit record, which alone keeps the operator's note, and with the
 * queued step that then declines the trade signal with `RefusedByOperator` in a transaction of its own.
 */
final readonly class RefuseOrderCommandHandler
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private AuditTrailInterface $auditTrail,
        private SignalOutcomeRecordingQueueInterface $signalOutcomeRecording,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private TransactionManagerInterface $transactionManager,
    ) {}

    /**
     * @throws OrderAlreadyDecidedException when another decision on the order came first
     * @throws UnknownOrderException
     */
    public function handle(RefuseOrderCommand $command): void
    {
        $orderId = self::orderIdOf($command->orderId);
        $operatorId = new OperatorId($command->operatorId);

        try {
            $this->transactionManager->transactional(function () use ($orderId, $operatorId, $command): void {
                $order = $this->orders->get($orderId);
                $order->refuse($this->clock->now());
                $this->orders->save($order);
                foreach ($order->releaseEvents() as $event) {
                    if ($event instanceof OrderRefused) {
                        $this->recordRefusal($event, $operatorId, $command->note);
                    }
                }
                $this->signalOutcomeRecording->enqueue($orderId);
            });
        } catch (OrderNotAwaitingApprovalException|OrderChangedConcurrentlyException $decidedFirst) {
            throw OrderAlreadyDecidedException::forOrder($orderId, $decidedFirst);
        } catch (OrderDoesNotExistException $missing) {
            throw UnknownOrderException::withId($command->orderId, $missing);
        }
    }

    private function recordRefusal(OrderRefused $event, OperatorId $operatorId, ?string $note): void
    {
        $values = [
            'previousLimitPrice' => $event->limitPrice->amount(),
            'limitPrice' => $event->limitPrice->amount(),
            'currency' => $event->limitPrice->currencyCode(),
            'previousQuantity' => (string) $event->quantity->units(),
            'quantity' => (string) $event->quantity->units(),
        ];
        if ($note !== null) {
            $values['note'] = $note;
        }
        $this->auditTrail->record(new AuditRecordDto('operator:'.$operatorId->toString(), $event->refusedAt, 'order', $event->id->toString(), 'refused', $values));
        $this->logger->info('The operator refused an order.', ['orderId' => $event->id->toString(), 'signalId' => $event->tradeSignalId->toString()]);
    }

    /**
     * @throws UnknownOrderException when the identity is not an order identity
     */
    private static function orderIdOf(string $id): OrderId
    {
        try {
            return new OrderId($id);
        } catch (InvalidOrderValueException $invalid) {
            throw UnknownOrderException::withId($id, $invalid);
        }
    }
}
