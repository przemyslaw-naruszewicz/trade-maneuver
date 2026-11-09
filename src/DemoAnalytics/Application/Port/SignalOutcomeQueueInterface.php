<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Application\Port;

interface SignalOutcomeQueueInterface
{
    /**
     * Passes the body of each message in the demo analytics application's outcome queue to `$handle`, and acknowledges
     * the message after `$handle` returns. A message whose `$handle` throws stays in the queue.
     *
     * @param  callable(string): void  $handle
     */
    public function takeEach(callable $handle): void;
}
