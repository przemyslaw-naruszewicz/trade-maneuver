<?php

declare(strict_types=1);

// A separate process with its own database connection. With `confirm <operator id> <code>` it confirms a TOTP code
// and prints `Confirmed`; with `create <e-mail address>` it creates an operator account and prints `Created`.
// When the use case refuses, it prints the short name of the exception instead.

use Illuminate\Contracts\Console\Kernel;
use TradeManeuver\Trading\Application\Operator\Command\ConfirmOperatorTotpCode\ConfirmOperatorTotpCodeCommand;
use TradeManeuver\Trading\Application\Operator\Command\ConfirmOperatorTotpCode\ConfirmOperatorTotpCodeCommandHandler;
use TradeManeuver\Trading\Application\Operator\Command\CreateOperator\CreateOperatorCommand;
use TradeManeuver\Trading\Application\Operator\Command\CreateOperator\CreateOperatorCommandHandler;

$root = dirname(__DIR__, 5);
require $root.'/vendor/autoload.php';
$application = require $root.'/bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();

try {
    if (($argv[1] ?? '') === 'confirm') {
        $application->make(ConfirmOperatorTotpCodeCommandHandler::class)
            ->handle(new ConfirmOperatorTotpCodeCommand($argv[2] ?? '', $argv[3] ?? ''));
        echo 'Confirmed';
    } else {
        $application->make(CreateOperatorCommandHandler::class)
            ->handle(new CreateOperatorCommand($argv[2] ?? '', 'a-second-operator-password'));
        echo 'Created';
    }
} catch (Throwable $exception) {
    echo new ReflectionClass($exception)->getShortName();
}
