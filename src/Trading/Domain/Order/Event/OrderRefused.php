<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Order\Event;

use DateTimeImmutable;
use TradeManeuver\Trading\Domain\Order\ValueObject\LimitPrice;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderQuantity;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

/**
 * The operator refused an order, which ends it with its limit price and quantity unchanged.
 */
final readonly class OrderRefused
{
    public function __construct(
        public OrderId $id,
        public TradeSignalId $tradeSignalId,
        public LimitPrice $limitPrice,
        public OrderQuantity $quantity,
        public DateTimeImmutable $refusedAt,
    ) {}
}
