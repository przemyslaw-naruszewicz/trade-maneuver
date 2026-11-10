<?php

declare(strict_types=1);

// A separate process with its own database connection: it creates an order with the identity given as the first
// argument for the stored trade signal of the order builder and adds it in a transaction. It prints `Added`, or the
// short name of the exception that refused the order.

use Illuminate\Contracts\Console\Kernel;
use Tests\Unit\Trading\Order\Support\OrderBuilder;
use TradeManeuver\Trading\Application\Port\TransactionManagerInterface;
use TradeManeuver\Trading\Domain\Order\Repository\OrderRepositoryInterface;

$root = dirname(__DIR__, 5);
require $root.'/vendor/autoload.php';
$application = require $root.'/bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();

$order = OrderBuilder::awaitingApproval($argv[1] ?? '');
$orders = $application->make(OrderRepositoryInterface::class);
try {
    $application->make(TransactionManagerInterface::class)->transactional(fn () => $orders->add($order));
    echo 'Added';
} catch (Throwable $exception) {
    echo new ReflectionClass($exception)->getShortName();
}
