<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Operator\Exception;

use RuntimeException;

/**
 * The TOTP code does not confirm the login; the message does not say why, so that it reveals nothing to a guesser.
 */
final class InvalidTotpCodeException extends RuntimeException
{
    public static function create(): self
    {
        return new self('This code is not valid, or it has already been used.');
    }
}
