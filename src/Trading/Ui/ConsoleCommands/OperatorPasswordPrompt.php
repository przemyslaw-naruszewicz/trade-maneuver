<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\ConsoleCommands;

use Illuminate\Console\Command;

/**
 * Asks for a new operator password twice without echo, so that a typing mistake cannot lock the operator out.
 */
final class OperatorPasswordPrompt
{
    /**
     * @return string|null the password, or null after the command has reported why it refused the input
     */
    public function ask(Command $command): ?string
    {
        $password = $command->secret('Password');
        $confirmation = $command->secret('Confirm the password');

        if (! is_string($password) || $password === '') {
            $command->error('The password must not be empty.');

            return null;
        }

        if ($password !== $confirmation) {
            $command->error('The passwords do not match.');

            return null;
        }

        return $password;
    }
}
