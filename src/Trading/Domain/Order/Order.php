<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Order;

use DateTimeImmutable;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\OrderSettings;
use TradeManeuver\Trading\Domain\Order\Dto\OrderStateDto;
use TradeManeuver\Trading\Domain\Order\Enum\ExecutionProgress;
use TradeManeuver\Trading\Domain\Order\Enum\OrderDirection;
use TradeManeuver\Trading\Domain\Order\Enum\OrderStatus;
use TradeManeuver\Trading\Domain\Order\Event\OrderApproved;
use TradeManeuver\Trading\Domain\Order\Event\OrderCreated;
use TradeManeuver\Trading\Domain\Order\Event\OrderRefused;
use TradeManeuver\Trading\Domain\Order\Exception\ApprovalLeavesZeroQuantityException;
use TradeManeuver\Trading\Domain\Order\Exception\InvalidOrderValueException;
use TradeManeuver\Trading\Domain\Order\Exception\OrderNotAwaitingApprovalException;
use TradeManeuver\Trading\Domain\Order\Exception\SignalValidityEndedException;
use TradeManeuver\Trading\Domain\Order\ValueObject\LimitPrice;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderQuantity;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Instrument;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

/**
 * A simulated order that Trade Maneuver creates for one trade signal under an investment configuration. It keeps a
 * snapshot of the order settings it used, so a later change to the investment configuration does not change it.
 */
final class Order
{
    /** @var list<OrderCreated|OrderApproved|OrderRefused> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly OrderId $id,
        private readonly TradeSignalId $tradeSignalId,
        private readonly InvestmentConfigurationId $investmentConfigurationId,
        private OrderStatus $status,
        private readonly ExecutionProgress $executionProgress,
        private readonly OrderDirection $direction,
        private readonly Instrument $instrument,
        private LimitPrice $limitPrice,
        private OrderQuantity $quantity,
        private readonly OrderSettings $settings,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $endedAt,
        private readonly int $version,
    ) {}

    /**
     * Creates a buy order that awaits the operator's approval, with no execution progress yet.
     */
    public static function createAwaitingApproval(
        OrderId $id,
        TradeSignalId $tradeSignalId,
        InvestmentConfigurationId $investmentConfigurationId,
        Instrument $instrument,
        LimitPrice $limitPrice,
        OrderQuantity $quantity,
        OrderSettings $settings,
        DateTimeImmutable $createdAt,
    ): self {
        $order = new self(
            $id,
            $tradeSignalId,
            $investmentConfigurationId,
            OrderStatus::AwaitingApproval,
            ExecutionProgress::None,
            OrderDirection::Buy,
            $instrument,
            $limitPrice,
            $quantity,
            $settings,
            $createdAt,
            null,
            1,
        );
        $order->recordedEvents[] = new OrderCreated(
            $id,
            $tradeSignalId,
            $investmentConfigurationId,
            OrderStatus::AwaitingApproval,
            OrderDirection::Buy,
            $instrument,
            $limitPrice,
            $quantity,
            $settings,
            $createdAt,
        );

        return $order;
    }

    /**
     * Rebuilds a stored order without recording domain events.
     */
    public static function reconstitute(OrderId $id, OrderStateDto $state): self
    {
        return new self(
            $id,
            $state->tradeSignalId,
            $state->investmentConfigurationId,
            $state->status,
            $state->executionProgress,
            $state->direction,
            $state->instrument,
            $state->limitPrice,
            $state->quantity,
            $state->settings,
            $state->createdAt,
            $state->endedAt,
            $state->version,
        );
    }

    public function id(): OrderId
    {
        return $this->id;
    }

    public function tradeSignalId(): TradeSignalId
    {
        return $this->tradeSignalId;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }

    public function state(): OrderStateDto
    {
        return new OrderStateDto(
            $this->tradeSignalId,
            $this->investmentConfigurationId,
            $this->status,
            $this->executionProgress,
            $this->direction,
            $this->instrument,
            $this->limitPrice,
            $this->quantity,
            $this->settings,
            $this->createdAt,
            $this->endedAt,
            $this->version,
        );
    }

    /**
     * Approves an order awaiting approval, optionally at a new limit price chosen by the operator. A new limit price
     * recalculates the quantity from the order amount of the snapshot; one at which the order amount buys no unit is
     * refused, and the order stays awaiting approval. Once signal validity has ended, the order can no longer be
     * approved; its lapse is a separate transition.
     *
     * @throws OrderNotAwaitingApprovalException
     * @throws SignalValidityEndedException
     * @throws InvalidOrderValueException when the new limit price is in another currency
     * @throws ApprovalLeavesZeroQuantityException
     */
    public function approve(?LimitPrice $newLimitPrice, DateTimeImmutable $validUntil, DateTimeImmutable $approvedAt): void
    {
        $this->ensureAwaitingApproval();
        if ($approvedAt >= $validUntil) {
            throw new SignalValidityEndedException(sprintf(
                'The signal validity of the order "%s" ended at %s, so it can no longer be approved.',
                $this->id->toString(),
                $validUntil->format(DATE_ATOM),
            ));
        }
        $previousLimitPrice = $this->limitPrice;
        $previousQuantity = $this->quantity;
        if ($newLimitPrice !== null) {
            $this->quantity = $this->quantityAt($newLimitPrice);
            $this->limitPrice = $newLimitPrice;
        }
        $this->status = OrderStatus::Approved;
        $this->recordedEvents[] = new OrderApproved(
            $this->id,
            $this->tradeSignalId,
            $previousLimitPrice,
            $this->limitPrice,
            $previousQuantity,
            $this->quantity,
            $approvedAt,
        );
    }

    /**
     * Refuses an order awaiting approval, which ends it.
     *
     * @throws OrderNotAwaitingApprovalException
     */
    public function refuse(DateTimeImmutable $refusedAt): void
    {
        $this->ensureAwaitingApproval();
        $this->status = OrderStatus::RefusedByOperator;
        $this->endedAt = $refusedAt;
        $this->recordedEvents[] = new OrderRefused($this->id, $this->tradeSignalId, $this->limitPrice, $this->quantity, $refusedAt);
    }

    /**
     * Each transition records one event, so a mutant that keeps just the first event is equivalent.
     *
     * @infection-ignore-all
     *
     * @return list<OrderCreated|OrderApproved|OrderRefused>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    /**
     * @throws OrderNotAwaitingApprovalException
     */
    private function ensureAwaitingApproval(): void
    {
        if ($this->status !== OrderStatus::AwaitingApproval) {
            throw new OrderNotAwaitingApprovalException(sprintf(
                'Only an order awaiting approval can be approved or refused, and the order "%s" is %s.',
                $this->id->toString(),
                $this->status->value,
            ));
        }
    }

    /**
     * @throws InvalidOrderValueException when the limit price is not in the currency of the order amount
     * @throws ApprovalLeavesZeroQuantityException
     */
    private function quantityAt(LimitPrice $limitPrice): OrderQuantity
    {
        $units = $limitPrice->unitsAffordableWith($this->settings->orderAmount);
        if ($units === 0) {
            throw new ApprovalLeavesZeroQuantityException(sprintf(
                'The order amount %s %s buys no unit at the limit price %s %s.',
                $this->settings->orderAmount->amount(),
                $limitPrice->currencyCode(),
                $limitPrice->amount(),
                $limitPrice->currencyCode(),
            ));
        }

        return OrderQuantity::of($units);
    }
}
