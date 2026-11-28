<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Order\Event;

use DateTimeImmutable;
use TradeManeuver\Trading\Domain\Order\ValueObject\LimitPrice;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderQuantity;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

/**
 * The operator approved an order, with the limit price and the quantity before and after the approval.
 */
final readonly class OrderApproved
{
    public function __construct(
        public OrderId $id,
        public TradeSignalId $tradeSignalId,
        public LimitPrice $previousLimitPrice,
        public LimitPrice $limitPrice,
        public OrderQuantity $previousQuantity,
        public OrderQuantity $quantity,
        public DateTimeImmutable $approvedAt,
    ) {}
}
