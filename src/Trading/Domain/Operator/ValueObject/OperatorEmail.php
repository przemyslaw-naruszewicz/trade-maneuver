<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Operator\ValueObject;

use TradeManeuver\Trading\Domain\Operator\Exception\InvalidOperatorValueException;

/**
 * The e-mail address the operator logs in with, kept in lower case so that a login matches it whatever the case.
 * The address is ASCII: the validation accepts no other characters.
 */
final readonly class OperatorEmail
{
    private string $value;

    /**
     * @throws InvalidOperatorValueException
     */
    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));
        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidOperatorValueException(sprintf('"%s" is not a valid e-mail address.', $value));
        }

        $this->value = $normalized;
    }

    public function toString(): string
    {
        return $this->value;
    }
}
