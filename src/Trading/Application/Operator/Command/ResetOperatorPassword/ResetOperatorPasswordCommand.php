<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Operator\Command\ResetOperatorPassword;

final readonly class ResetOperatorPasswordCommand
{
    public function __construct(
        public string $email,
        #[\SensitiveParameter] public string $password,
    ) {}
}
