<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Order\Command\ApproveOrder;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use TradeManeuver\Trading\Application\Audit\AuditRecordDto;
use TradeManeuver\Trading\Application\Order\Exception\OrderAlreadyDecidedException;
use TradeManeuver\Trading\Application\Order\Exception\UnknownOrderException;
use TradeManeuver\Trading\Application\Port\AuditTrailInterface;
use TradeManeuver\Trading\Application\Port\TransactionManagerInterface;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorId;
use TradeManeuver\Trading\Domain\Order\Event\OrderApproved;
use TradeManeuver\Trading\Domain\Order\Exception\ApprovalLeavesZeroQuantityException;
use TradeManeuver\Trading\Domain\Order\Exception\InvalidOrderValueException;
use TradeManeuver\Trading\Domain\Order\Exception\OrderNotAwaitingApprovalException;
use TradeManeuver\Trading\Domain\Order\Exception\SignalValidityEndedException;
use TradeManeuver\Trading\Domain\Order\Repository\Exception\OrderChangedConcurrentlyException;
use TradeManeuver\Trading\Domain\Order\Repository\Exception\OrderDoesNotExistException;
use TradeManeuver\Trading\Domain\Order\Repository\OrderRepositoryInterface;
use TradeManeuver\Trading\Domain\Order\ValueObject\LimitPrice;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;
use TradeManeuver\Trading\Domain\TradeSignal\Repository\TradeSignalRepositoryInterface;

/**
 * Approves an order in one transaction with its audit record. The end of signal validity is a fact of the order's
 * trade signal, which the approval reads but does not change.
 */
final readonly class ApproveOrderCommandHandler
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private TradeSignalRepositoryInterface $tradeSignals,
        private AuditTrailInterface $auditTrail,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private TransactionManagerInterface $transactionManager,
    ) {}

    /**
     * @throws InvalidOrderValueException when the new limit price is not a valid limit price in the order currency
     * @throws ApprovalLeavesZeroQuantityException
     * @throws SignalValidityEndedException
     * @throws OrderAlreadyDecidedException when another decision on the order came first
     * @throws UnknownOrderException
     */
    public function handle(ApproveOrderCommand $command): void
    {
        $orderId = self::orderIdOf($command->orderId);
        $operatorId = new OperatorId($command->operatorId);
        $newLimitPriceAmount = $command->newLimitPriceAmount;

        try {
            $this->transactionManager->transactional(function () use ($orderId, $operatorId, $newLimitPriceAmount): void {
                $order = $this->orders->get($orderId);
                $newLimitPrice = $newLimitPriceAmount === null ? null : LimitPrice::of($newLimitPriceAmount, $order->limitPrice()->currencyCode());
                $validUntil = $this->tradeSignals->get($order->tradeSignalId())->validUntil();
                $order->approve($newLimitPrice, $validUntil, $this->clock->now());
                $this->orders->save($order);
                foreach ($order->releaseEvents() as $event) {
                    if ($event instanceof OrderApproved) {
                        $this->recordApproval($event, $operatorId);
                    }
                }
            });
        } catch (OrderNotAwaitingApprovalException|OrderChangedConcurrentlyException $decidedFirst) {
            throw OrderAlreadyDecidedException::forOrder($orderId, $decidedFirst);
        } catch (OrderDoesNotExistException $missing) {
            throw UnknownOrderException::withId($command->orderId, $missing);
        }
    }

    private function recordApproval(OrderApproved $event, OperatorId $operatorId): void
    {
        $this->auditTrail->record(new AuditRecordDto('operator:'.$operatorId->toString(), $event->approvedAt, 'order', $event->id->toString(), 'approved', [
            'previousLimitPrice' => $event->previousLimitPrice->amount(),
            'limitPrice' => $event->limitPrice->amount(),
            'currency' => $event->limitPrice->currencyCode(),
            'previousQuantity' => (string) $event->previousQuantity->units(),
            'quantity' => (string) $event->quantity->units(),
        ]));
        $this->logger->info('The operator approved an order.', ['orderId' => $event->id->toString(), 'signalId' => $event->tradeSignalId->toString()]);
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
