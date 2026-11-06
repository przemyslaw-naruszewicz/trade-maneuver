<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use TradeManeuver\Trading\Application\Port\TransactionManagerInterface;

/**
 * Runs the operation in a transaction on the connection that repositories and the audit trail also use.
 */
final readonly class LaravelTransactionManager implements TransactionManagerInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function transactional(callable $operation): mixed
    {
        return $this->connection->transaction($operation(...));
    }
}
