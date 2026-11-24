<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Operator\Dto;

use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorEmail;
use TradeManeuver\Trading\Domain\Operator\ValueObject\PasswordHash;
use TradeManeuver\Trading\Domain\Operator\ValueObject\TotpSecret;

final readonly class OperatorStateDto
{
    public function __construct(
        public OperatorEmail $email,
        public PasswordHash $passwordHash,
        public ?TotpSecret $totpSecret,
    ) {}
}
