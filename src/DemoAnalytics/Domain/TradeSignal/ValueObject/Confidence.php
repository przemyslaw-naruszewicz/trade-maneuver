<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject;

use Brick\Math\BigDecimal;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\Exception\InvalidTradeSignalException;

/**
 * A decimal from 0 to 1 inclusive, kept as it was given.
 */
final readonly class Confidence
{
    private const string PATTERN = '/^\d+(\.\d+)?$/D';

    /**
     * @throws InvalidTradeSignalException
     */
    public function __construct(private string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1 || BigDecimal::of($value)->isGreaterThan(1)) {
            throw new InvalidTradeSignalException('The confidence must be a decimal from 0 to 1, such as 0.75.');
        }
    }

    public function toString(): string
    {
        return $this->value;
    }
}
