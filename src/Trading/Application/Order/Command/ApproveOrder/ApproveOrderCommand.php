<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Order\Command\ApproveOrder;

/**
 * The operator's approval of an order awaiting approval. Without a new limit price, the order keeps its limit price
 * and quantity. The operator cannot change the instrument, the order amount, or the quantity, so the command has no
 * place for them.
 */
final readonly class ApproveOrderCommand
{
    public function __construct(
        public string $orderId,
        public string $operatorId,
        public ?string $newLimitPriceAmount = null,
        public ?string $newLimitPriceCurrency = null,
    ) {}
}
