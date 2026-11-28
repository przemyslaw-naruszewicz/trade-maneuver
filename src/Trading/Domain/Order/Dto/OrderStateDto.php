<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Order\Dto;

use DateTimeImmutable;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\OrderSettings;
use TradeManeuver\Trading\Domain\Order\Enum\ExecutionProgress;
use TradeManeuver\Trading\Domain\Order\Enum\OrderDirection;
use TradeManeuver\Trading\Domain\Order\Enum\OrderStatus;
use TradeManeuver\Trading\Domain\Order\ValueObject\LimitPrice;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderQuantity;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Instrument;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

final readonly class OrderStateDto
{
    public function __construct(
        public TradeSignalId $tradeSignalId,
        public InvestmentConfigurationId $investmentConfigurationId,
        public OrderStatus $status,
        public ExecutionProgress $executionProgress,
        public OrderDirection $direction,
        public Instrument $instrument,
        public LimitPrice $limitPrice,
        public OrderQuantity $quantity,
        public OrderSettings $settings,
        public DateTimeImmutable $createdAt,
        // When the order reached a terminal status; null while it has not.
        public ?DateTimeImmutable $endedAt,
        // The version the order was created or loaded at; saving it requires that the stored version is still this one.
        public int $version,
    ) {}
}
