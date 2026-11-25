<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\ConsoleCommands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use TradeManeuver\Trading\Application\Operator\Command\EnrolOperatorTotp\EnrolOperatorTotpCommand as EnrolOperatorTotp;
use TradeManeuver\Trading\Application\Operator\Command\EnrolOperatorTotp\EnrolOperatorTotpCommandHandler;
use TradeManeuver\Trading\Domain\Operator\Exception\OperatorException;

#[Signature('trading:enrol-operator-totp
    {email : The operator account e-mail address}')]
#[Description('Enrol or reset the operator TOTP and print its secret once')]
final class EnrolOperatorTotpCommand extends Command
{
    public function handle(EnrolOperatorTotpCommandHandler $handler): int
    {
        try {
            $enrolment = $handler->handle(new EnrolOperatorTotp($this->argument('email')));
        } catch (OperatorException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('TOTP is enrolled. Add the secret to an authenticator app now: it is not shown again.');
        $this->line('Secret: '.$enrolment->secret);
        $this->line('URI: '.$enrolment->provisioningUri);

        return self::SUCCESS;
    }
}
