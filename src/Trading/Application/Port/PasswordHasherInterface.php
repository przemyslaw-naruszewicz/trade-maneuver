<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Port;

use TradeManeuver\Trading\Domain\Operator\ValueObject\PasswordHash;

interface PasswordHasherInterface
{
    public function hash(#[\SensitiveParameter] string $password): PasswordHash;
}
