<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Application\Port;

use TradeManeuver\DemoAnalytics\Application\TradeSignal\Exception\TradeSignalNotPublishedException;

interface TradeSignalPublisherInterface
{
    /**
     * Publishes a `tm.trade-signal.v1` message body as a persistent message and returns once the message broker has
     * confirmed it.
     *
     * @throws TradeSignalNotPublishedException
     */
    public function publish(string $messageId, string $body): void;
}
