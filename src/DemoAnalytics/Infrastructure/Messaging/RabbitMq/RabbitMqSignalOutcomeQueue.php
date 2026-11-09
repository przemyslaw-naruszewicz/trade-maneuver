<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Infrastructure\Messaging\RabbitMq;

use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use TradeManeuver\DemoAnalytics\Application\Port\SignalOutcomeQueueInterface;

/**
 * Gets the messages of the outcome queue one by one with manual acknowledgement. Closing the connection returns a
 * message that was not acknowledged to the queue.
 */
final readonly class RabbitMqSignalOutcomeQueue implements SignalOutcomeQueueInterface
{
    public function __construct(private AMQPConnectionConfig $connectionConfig, private string $queue) {}

    public function takeEach(callable $handle): void
    {
        $connection = AMQPConnectionFactory::create($this->connectionConfig);
        try {
            $channel = $connection->channel();
            while (($message = $channel->basic_get($this->queue)) !== null) {
                $handle($message->getBody());
                $message->ack();
            }
        } finally {
            $connection->close();
        }
    }
}
