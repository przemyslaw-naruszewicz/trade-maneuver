<?php

declare(strict_types=1);

// A separate worker process with its own database connection: it reads a message body from standard input,
// passes it to the intake use case as published by the RabbitMQ user given as the first argument, and prints the result.

use Illuminate\Contracts\Console\Kernel;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommand;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommandHandler;

$root = dirname(__DIR__, 5);
require $root.'/vendor/autoload.php';
$application = require $root.'/bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();

$publisher = $argv[1] ?? '';
config(['trading.analytics_applications' => [['id' => 'demo-analytics', 'rabbitmq_user' => $publisher]]]);

$result = $application->make(ReceiveTradeSignalCommandHandler::class)->handle(
    new ReceiveTradeSignalCommand((string) stream_get_contents(STDIN), $publisher),
);
echo $result->name;
