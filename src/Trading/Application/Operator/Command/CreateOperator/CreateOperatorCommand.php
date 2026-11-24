<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Operator\Command\CreateOperator;

final readonly class CreateOperatorCommand
{
    public function __construct(
        public string $email,
        #[\SensitiveParameter] public string $password,
    ) {}
}
