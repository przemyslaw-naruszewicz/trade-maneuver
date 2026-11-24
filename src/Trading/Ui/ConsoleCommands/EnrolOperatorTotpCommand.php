<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\ConsoleCommands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trading:enrol-operator-totp
    {email : The operator account e-mail address}')]
#[Description('Enrol or reset the operator TOTP and print its secret once')]
final class EnrolOperatorTotpCommand extends Command
{
    public function handle(): int
    {
        return self::SUCCESS;
    }
}
