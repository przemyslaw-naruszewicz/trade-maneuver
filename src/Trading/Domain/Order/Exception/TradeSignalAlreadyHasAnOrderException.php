<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Order\Exception;

use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

final class TradeSignalAlreadyHasAnOrderException extends OrderException
{
    public static function forTradeSignal(TradeSignalId $tradeSignalId): self
    {
        return new self(sprintf('The trade signal "%s" already has an order.', $tradeSignalId->toString()));
    }
}
