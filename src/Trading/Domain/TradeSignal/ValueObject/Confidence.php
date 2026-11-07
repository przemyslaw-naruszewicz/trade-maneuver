<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\ValueObject;

use Brick\Math\BigDecimal;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\PlainDecimal;
use TradeManeuver\Trading\Domain\TradeSignal\Exception\InvalidTradeSignalValueException;

/**
 * An exact decimal from 0 to 1 inclusive, kept with the scale the analytics application sent.
 */
final readonly class Confidence
{
    private const string MESSAGE = 'The confidence must be a decimal string from 0 to 1, such as 0.85.';

    private function __construct(private BigDecimal $value) {}

    /**
     * @throws InvalidTradeSignalValueException
     */
    public static function of(string $value): self
    {
        if (! PlainDecimal::matches($value)) {
            throw new InvalidTradeSignalValueException(self::MESSAGE);
        }

        $decimal = BigDecimal::of($value);
        if ($decimal->isNegative() || $decimal->isGreaterThan(1)) {
            throw new InvalidTradeSignalValueException(self::MESSAGE);
        }

        return new self($decimal);
    }

    public function toString(): string
    {
        return $this->value->toString();
    }
}
