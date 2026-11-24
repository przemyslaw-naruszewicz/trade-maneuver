<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Operator\ValueObject;

use TradeManeuver\Trading\Domain\Operator\Exception\InvalidOperatorValueException;

/**
 * The shared TOTP secret in Base32, as an authenticator app reads it.
 */
final readonly class TotpSecret
{
    private const string BASE32_PATTERN = '/^[A-Z2-7]{16,}$/D';

    /**
     * @throws InvalidOperatorValueException
     */
    public function __construct(#[\SensitiveParameter] private string $value)
    {
        if (preg_match(self::BASE32_PATTERN, $value) !== 1) {
            throw new InvalidOperatorValueException('The TOTP secret must be at least 16 Base32 characters.');
        }
    }

    public function toString(): string
    {
        return $this->value;
    }
}
