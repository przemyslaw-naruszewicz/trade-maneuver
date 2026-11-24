<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Operator\Command\EnrolOperatorTotp;

final readonly class EnrolOperatorTotpCommand
{
    public function __construct(public string $email) {}
}
