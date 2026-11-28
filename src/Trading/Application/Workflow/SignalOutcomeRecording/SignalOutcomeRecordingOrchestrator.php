<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Workflow\SignalOutcomeRecording;

use LogicException;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use TradeManeuver\Trading\Application\Port\TransactionManagerInterface;
use TradeManeuver\Trading\Application\TradeSignal\SignalOutcome\SignalOutcomeRecorder;
use TradeManeuver\Trading\Domain\Order\Repository\OrderRepositoryInterface;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalDeclined;
use TradeManeuver\Trading\Domain\TradeSignal\Repository\TradeSignalRepositoryInterface;
use TradeManeuver\Trading\Domain\TradeSignal\TradeSignal;

/**
 * Records the signal outcome of an order's trade signal after the order reached a terminal status. The order's
 * terminal status and this step commit in separate transactions, one aggregate each: the transaction that ends the
 * order queues this step, so it runs after a crash between the two. The trade signal stays locked during the step,
 * and a trade signal that already has its signal outcome is skipped, so a step that runs twice records one outcome.
 */
final readonly class SignalOutcomeRecordingOrchestrator
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private TradeSignalRepositoryInterface $tradeSignals,
        private SignalOutcomeRecorder $signalOutcomeRecorder,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private TransactionManagerInterface $transactionManager,
    ) {}

    public function record(OrderId $orderId): void
    {
        $this->transactionManager->transactional(fn () => $this->recordInTransaction($orderId));
    }

    private function recordInTransaction(OrderId $orderId): void
    {
        $order = $this->orders->get($orderId);
        if (! $order->hasEnded()) {
            throw new LogicException(sprintf('The order "%s" has not reached a terminal status, so its trade signal has no signal outcome yet.', $orderId->toString()));
        }

        $tradeSignal = $this->tradeSignals->getLocked($order->tradeSignalId());
        if ($tradeSignal->outcome() !== null) {
            // An earlier run of the step recorded it.
            $this->logger->info('The trade signal of an ended order already has its signal outcome.', ['orderId' => $orderId->toString(), 'signalId' => $order->tradeSignalId()->toString()]);

            return;
        }

        $tradeSignal->endAfterOrder($order->status(), $this->tradeSignals->nextOutcomeIdentity(), $this->clock->now());
        $this->tradeSignals->save($tradeSignal);
        $this->recordSignalOutcome($tradeSignal);
    }

    private function recordSignalOutcome(TradeSignal $tradeSignal): void
    {
        foreach ($tradeSignal->releaseEvents() as $event) {
            if (! $event instanceof TradeSignalDeclined) {
                throw new LogicException(sprintf('The end of an order declines its trade signal and records nothing else, not %s.', $event::class));
            }
            $this->signalOutcomeRecorder->record($event);
        }
    }
}
