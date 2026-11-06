<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Port;

/**
 * The use-case transaction boundary: everything the operation writes commits together or not at all.
 */
interface TransactionManagerInterface
{
    /**
     * @template TResult
     *
     * @param  callable(): TResult  $operation
     * @return TResult
     */
    public function transactional(callable $operation): mixed;
}
