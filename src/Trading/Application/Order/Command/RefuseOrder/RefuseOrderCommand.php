<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Order\Command\RefuseOrder;

/**
 * The operator's refusal of an order awaiting approval, with an optional note that only the audit trail keeps.
 */
final readonly class RefuseOrderCommand
{
    public function __construct(
        public string $orderId,
        public string $operatorId,
        public ?string $note = null,
    ) {}
}
