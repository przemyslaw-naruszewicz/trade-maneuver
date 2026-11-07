<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\Exception;

/**
 * A value that a trade signal cannot hold. `part` names the component of a composite value that is wrong, if any.
 */
final class InvalidTradeSignalValueException extends TradeSignalException
{
    public function __construct(string $message, public readonly ?string $part = null)
    {
        parent::__construct($message);
    }
}
