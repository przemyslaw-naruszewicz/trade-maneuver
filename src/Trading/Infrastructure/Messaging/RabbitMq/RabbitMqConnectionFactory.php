<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\RabbitMq;

use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Opens connections as Trade Maneuver's own RabbitMQ user, configured in `trading.rabbitmq`.
 */
final readonly class RabbitMqConnectionFactory
{
    public function __construct(
        private string $host,
        private int $port,
        private string $vhost,
        private string $user,
        private string $password,
    ) {}

    public function connect(): AMQPStreamConnection
    {
        return new AMQPStreamConnection($this->host, $this->port, $this->user, $this->password, $this->vhost);
    }
}
