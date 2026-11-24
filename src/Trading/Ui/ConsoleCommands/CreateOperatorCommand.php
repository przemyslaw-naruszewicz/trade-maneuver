<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\ConsoleCommands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trading:create-operator
    {email : The operator account e-mail address}')]
#[Description('Create the operator account')]
final class CreateOperatorCommand extends Command
{
    public function handle(): int
    {
        $this->secret('Password');
        $this->secret('Confirm the password');

        return self::SUCCESS;
    }
}
