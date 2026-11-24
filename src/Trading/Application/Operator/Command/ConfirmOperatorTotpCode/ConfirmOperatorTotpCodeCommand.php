<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Operator\Command\ConfirmOperatorTotpCode;

final readonly class ConfirmOperatorTotpCodeCommand
{
    public function __construct(
        public string $operatorId,
        #[\SensitiveParameter] public string $code,
    ) {}
}
