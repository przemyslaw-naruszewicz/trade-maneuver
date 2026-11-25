<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Operator\ValueObject;

use TradeManeuver\Trading\Domain\Operator\Exception\InvalidOperatorValueException;

/**
 * A one-way hash of the operator password; the plain password is never kept.
 */
final readonly class PasswordHash
{
    /**
     * @throws InvalidOperatorValueException
     */
    public function __construct(#[\SensitiveParameter] private string $value)
    {
        if ($value === '') {
            throw new InvalidOperatorValueException('The password hash must not be empty.');
        }
    }

    public function toString(): string
    {
        return $this->value;
    }
}
