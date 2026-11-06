<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject;

/**
 * Brick accepts exponents and fractions, so external decimal strings are checked against the plain form first.
 */
final class PlainDecimal
{
    private const string PATTERN = '/^-?\d+(\.\d+)?$/D';

    public static function matches(string $value): bool
    {
        return preg_match(self::PATTERN, $value) === 1;
    }
}
