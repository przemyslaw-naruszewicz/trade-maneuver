<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Operator;

use Illuminate\Contracts\Hashing\Hasher;
use TradeManeuver\Trading\Application\Port\PasswordHasherInterface;
use TradeManeuver\Trading\Domain\Operator\ValueObject\PasswordHash;

/**
 * Hashes with the application's configured hasher, which the session guard also uses to check the password.
 */
final readonly class LaravelPasswordHasher implements PasswordHasherInterface
{
    public function __construct(private Hasher $hasher) {}

    public function hash(#[\SensitiveParameter] string $password): PasswordHash
    {
        return new PasswordHash($this->hasher->make($password));
    }
}
