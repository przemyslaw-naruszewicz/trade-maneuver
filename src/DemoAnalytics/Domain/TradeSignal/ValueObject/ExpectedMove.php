<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject;

use Brick\Math\BigDecimal;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\Exception\InvalidTradeSignalException;

/**
 * The expected price move as a range of decimal percentages, kept as they were given, with the minimum not above the
 * maximum.
 */
final readonly class ExpectedMove
{
    private const string PATTERN = '/^-?\d+(\.\d+)?$/D';

    /**
     * @throws InvalidTradeSignalException
     */
    public function __construct(private string $min, private string $max)
    {
        self::checkPercentage($min, 'minimum', '1.0');
        self::checkPercentage($max, 'maximum', '3.0');
        if (BigDecimal::of($min)->isGreaterThan($max)) {
            throw new InvalidTradeSignalException('The expected move minimum must not be greater than its maximum.');
        }
    }

    public function min(): string
    {
        return $this->min;
    }

    public function max(): string
    {
        return $this->max;
    }

    private static function checkPercentage(string $value, string $part, string $example): void
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidTradeSignalException(sprintf('The expected move %s must be a decimal percentage, such as %s.', $part, $example));
        }
    }
}
