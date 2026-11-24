<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\ConsoleCommands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use TradeManeuver\Trading\Application\Operator\Command\ResetOperatorPassword\ResetOperatorPasswordCommand as ResetOperatorPassword;
use TradeManeuver\Trading\Application\Operator\Command\ResetOperatorPassword\ResetOperatorPasswordCommandHandler;
use TradeManeuver\Trading\Domain\Operator\Exception\OperatorException;

#[Signature('trading:reset-operator-password
    {email : The operator account e-mail address}')]
#[Description('Reset the operator password')]
final class ResetOperatorPasswordCommand extends Command
{
    public function handle(ResetOperatorPasswordCommandHandler $handler, OperatorPasswordPrompt $passwordPrompt): int
    {
        $password = $passwordPrompt->ask($this);
        if ($password === null) {
            return self::FAILURE;
        }

        try {
            $handler->handle(new ResetOperatorPassword($this->argument('email'), $password));
        } catch (OperatorException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('The operator password was reset.');

        return self::SUCCESS;
    }
}
