<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\ConsoleCommands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use TradeManeuver\Trading\Application\Operator\Command\CreateOperator\CreateOperatorCommand as CreateOperator;
use TradeManeuver\Trading\Application\Operator\Command\CreateOperator\CreateOperatorCommandHandler;
use TradeManeuver\Trading\Domain\Operator\Exception\OperatorException;

#[Signature('trading:create-operator
    {email : The operator account e-mail address}')]
#[Description('Create the operator account')]
final class CreateOperatorCommand extends Command
{
    public function handle(CreateOperatorCommandHandler $handler, OperatorPasswordPrompt $passwordPrompt): int
    {
        $password = $passwordPrompt->ask($this);
        if ($password === null) {
            return self::FAILURE;
        }

        $email = $this->argument('email');

        try {
            $handler->handle(new CreateOperator($email, $password));
        } catch (OperatorException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('The operator account was created.');

        return self::SUCCESS;
    }
}
