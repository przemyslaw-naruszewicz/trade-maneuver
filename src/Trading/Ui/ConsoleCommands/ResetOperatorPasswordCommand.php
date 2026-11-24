<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\ConsoleCommands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trading:reset-operator-password
    {email : The operator account e-mail address}')]
#[Description('Reset the operator password')]
final class ResetOperatorPasswordCommand extends Command
{
    public function handle(): int
    {
        $this->secret('Password');
        $this->secret('Confirm the password');

        return self::SUCCESS;
    }
}
