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
use TradeManeuver\Trading\Domain\Order\Event\OrderCreated;
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
    /** @var list<OrderCreated> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly OrderId $id,
        private readonly TradeSignalId $tradeSignalId,
        private readonly InvestmentConfigurationId $investmentConfigurationId,
        private readonly OrderStatus $status,
        private readonly ExecutionProgress $executionProgress,
        private readonly OrderDirection $direction,
        private readonly Instrument $instrument,
        private readonly LimitPrice $limitPrice,
        private readonly OrderQuantity $quantity,
        private readonly OrderSettings $settings,
        private readonly DateTimeImmutable $createdAt,
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
        );
    }

    public function id(): OrderId
    {
        return $this->id;
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
        );
    }

    /**
     * Only creation records an event, so a mutant that keeps just the first event is equivalent.
     *
     * @infection-ignore-all
     *
     * @return list<OrderCreated>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
