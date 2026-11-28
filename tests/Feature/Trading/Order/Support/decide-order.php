<?php

declare(strict_types=1);

// A separate process with its own database connection: it refuses the order with the identity given as the first
// argument, as the operator with the identity given as the second. It prints `Refused`, or the short name of the
// exception that refused the decision.

use Illuminate\Contracts\Console\Kernel;
use TradeManeuver\Trading\Application\Order\Command\RefuseOrder\RefuseOrderCommand;
use TradeManeuver\Trading\Application\Order\Command\RefuseOrder\RefuseOrderCommandHandler;

$root = dirname(__DIR__, 5);
require $root.'/vendor/autoload.php';
$application = require $root.'/bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();

try {
    $application->make(RefuseOrderCommandHandler::class)->handle(new RefuseOrderCommand($argv[1] ?? '', $argv[2] ?? '', 'Refused in a second tab.'));
    echo 'Refused';
} catch (Throwable $exception) {
    echo new ReflectionClass($exception)->getShortName();
}
