<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Order\Event;

use DateTimeImmutable;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\OrderSettings;
use TradeManeuver\Trading\Domain\Order\Enum\OrderDirection;
use TradeManeuver\Trading\Domain\Order\Enum\OrderStatus;
use TradeManeuver\Trading\Domain\Order\ValueObject\LimitPrice;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderQuantity;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Instrument;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

final readonly class OrderCreated
{
    public function __construct(
        public OrderId $id,
        public TradeSignalId $tradeSignalId,
        public InvestmentConfigurationId $investmentConfigurationId,
        public OrderStatus $status,
        public OrderDirection $direction,
        public Instrument $instrument,
        public LimitPrice $limitPrice,
        public OrderQuantity $quantity,
        public OrderSettings $settings,
        public DateTimeImmutable $createdAt,
    ) {}
}
