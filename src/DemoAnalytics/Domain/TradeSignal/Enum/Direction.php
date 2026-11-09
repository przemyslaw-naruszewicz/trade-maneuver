<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Domain\TradeSignal\Enum;

use TradeManeuver\DemoAnalytics\Domain\TradeSignal\Exception\InvalidTradeSignalException;

enum Direction: string
{
    case Long = 'long';
    case Short = 'short';

    /**
     * @throws InvalidTradeSignalException
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw new InvalidTradeSignalException('The direction must be long or short.');
    }
}
