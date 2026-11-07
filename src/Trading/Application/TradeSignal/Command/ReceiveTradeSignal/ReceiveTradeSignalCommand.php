<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal;

/**
 * A delivered `tm.trade-signal.v1` message: its raw body and the authenticated identity of its publisher, the
 * RabbitMQ user given by the AMQP `user_id` property, or null when the delivery carries none.
 */
final readonly class ReceiveTradeSignalCommand
{
    public function __construct(
        public string $body,
        public ?string $publisherIdentity,
    ) {}
}
