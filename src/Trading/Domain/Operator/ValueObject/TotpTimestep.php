<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Operator\ValueObject;

use TradeManeuver\Trading\Domain\Operator\Exception\InvalidOperatorValueException;

/**
 * The number of 30-second steps since the Unix epoch at which a TOTP code is valid.
 */
final readonly class TotpTimestep
{
    /**
     * @throws InvalidOperatorValueException
     */
    public function __construct(private int $value)
    {
        if ($value < 0) {
            throw new InvalidOperatorValueException('A TOTP time step must not be negative.');
        }
    }

    public function isAfter(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function value(): int
    {
        return $this->value;
    }
}
