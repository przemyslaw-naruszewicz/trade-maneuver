<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Order\Command\RefuseOrder;

use Psr\Clock\ClockInterface;
use TradeManeuver\Trading\Application\DomainEvent\DomainEventContext;
use TradeManeuver\Trading\Application\Order\Exception\OrderAlreadyDecidedException;
use TradeManeuver\Trading\Application\Order\Exception\UnknownOrderException;
use TradeManeuver\Trading\Application\Port\DomainEventBusInterface;
use TradeManeuver\Trading\Application\Port\SignalOutcomeRecordingQueueInterface;
use TradeManeuver\Trading\Application\Port\TransactionManagerInterface;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorId;
use TradeManeuver\Trading\Domain\Order\Exception\InvalidOrderValueException;
use TradeManeuver\Trading\Domain\Order\Exception\OrderNotAwaitingApprovalException;
use TradeManeuver\Trading\Domain\Order\Repository\Exception\OrderChangedConcurrentlyException;
use TradeManeuver\Trading\Domain\Order\Repository\Exception\OrderDoesNotExistException;
use TradeManeuver\Trading\Domain\Order\Repository\OrderRepositoryInterface;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;

/**
 * Refuses an order in one transaction with the handlers of its domain events, which get the operator's note for the
 * audit record, and with the queued step that then declines the trade signal with `RefusedByOperator` in a
 * transaction of its own.
 */
final readonly class RefuseOrderCommandHandler
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private DomainEventBusInterface $domainEvents,
        private SignalOutcomeRecordingQueueInterface $signalOutcomeRecording,
        private ClockInterface $clock,
        private TransactionManagerInterface $transactionManager,
    ) {}

    /**
     * @throws OrderAlreadyDecidedException when another decision on the order came first
     * @throws UnknownOrderException
     */
    public function handle(RefuseOrderCommand $command): void
    {
        $orderId = self::orderIdOf($command->orderId);
        $context = DomainEventContext::ofOperator(
            new OperatorId($command->operatorId),
            $command->note === null ? [] : ['note' => $command->note],
        );

        try {
            $this->transactionManager->transactional(function () use ($orderId, $context): void {
                $order = $this->orders->get($orderId);
                $order->refuse($this->clock->now());
                $this->orders->save($order);
                $this->domainEvents->dispatch($context, ...$order->releaseEvents());
                $this->signalOutcomeRecording->enqueue($orderId);
            });
        } catch (OrderNotAwaitingApprovalException|OrderChangedConcurrentlyException $decidedFirst) {
            throw OrderAlreadyDecidedException::forOrder($orderId, $decidedFirst);
        } catch (OrderDoesNotExistException $missing) {
            throw UnknownOrderException::withId($command->orderId, $missing);
        }
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
