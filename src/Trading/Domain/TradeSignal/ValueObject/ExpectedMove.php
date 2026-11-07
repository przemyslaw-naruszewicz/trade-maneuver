<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\ValueObject;

use Brick\Math\BigDecimal;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\PlainDecimal;
use TradeManeuver\Trading\Domain\TradeSignal\Exception\InvalidTradeSignalValueException;

/**
 * The expected price move as a range of exact decimal percentages, with the minimum not above the maximum.
 */
final readonly class ExpectedMove
{
    private function __construct(private BigDecimal $min, private BigDecimal $max) {}

    /**
     * @throws InvalidTradeSignalValueException
     */
    public static function of(string $min, string $max): self
    {
        $minimum = self::percentageOf($min, 'min');
        $maximum = self::percentageOf($max, 'max');
        if ($minimum->isGreaterThan($maximum)) {
            throw new InvalidTradeSignalValueException('The expected move minimum must not be greater than its maximum.');
        }

        return new self($minimum, $maximum);
    }

    public function min(): string
    {
        return $this->min->toString();
    }

    public function max(): string
    {
        return $this->max->toString();
    }

    private static function percentageOf(string $value, string $part): BigDecimal
    {
        if (! PlainDecimal::matches($value)) {
            throw new InvalidTradeSignalValueException(sprintf('The expected move %s must be a decimal percentage string, such as 2.5.', $part), $part);
        }

        return BigDecimal::of($value);
    }
}
