<?php

declare(strict_types=1);

namespace Tests\Support\Trading;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Connects to the RabbitMQ vhost of the tests as one of the configured users: Trade Maneuver or the producer user of a
 * fictional analytics application. Each producer's password comes from the same environment variable as RabbitMQ's.
 */
final class RabbitMqTestClient
{
    public const string INTAKE_QUEUE = 'tm.trade-signals.intake';

    public const string DEAD_LETTER_QUEUE = 'tm.trade-signals.dead-letter';

    private const array PASSWORD_VARIABLES = [
        'demo-analytics' => 'DEMO_ANALYTICS_RABBITMQ_PASSWORD',
        'demo-research' => 'DEMO_RESEARCH_RABBITMQ_PASSWORD',
    ];

    private AMQPStreamConnection $connection;

    private function __construct(string $user, string $password)
    {
        $this->connection = new AMQPStreamConnection(
            (string) config('trading.rabbitmq.host'),
            (int) config('trading.rabbitmq.port'),
            $user,
            $password,
            (string) config('trading.rabbitmq.vhost'),
        );
    }

    public static function asTradeManeuver(): self
    {
        return new self((string) config('trading.rabbitmq.user'), (string) config('trading.rabbitmq.password'));
    }

    public static function asProducer(string $user): self
    {
        return new self($user, (string) env(self::PASSWORD_VARIABLES[$user]));
    }

    public static function outcomeQueueOf(string $analyticsApplication): string
    {
        return 'tm.signal-outcomes.'.$analyticsApplication;
    }

    public function channel(): AMQPChannel
    {
        return $this->connection->channel();
    }

    /**
     * Publishes a persistent trade signal to tm.trade-signals with the given AMQP `user_id`, and waits for the
     * publisher confirmation.
     */
    public function publishTradeSignal(string $body, ?string $userId): void
    {
        $properties = ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT, 'content_type' => 'application/json'];
        if ($userId !== null) {
            $properties['user_id'] = $userId;
        }
        $channel = $this->channel();
        $channel->confirm_select();
        $channel->basic_publish(new AMQPMessage($body, $properties), 'tm.trade-signals');
        $channel->wait_for_pending_acks(5.0);
        $channel->close();
    }

    /**
     * Takes the next message from the queue and acknowledges it, or returns null when the queue is empty.
     */
    public function take(string $queue): ?AMQPMessage
    {
        $channel = $this->channel();
        $message = $channel->basic_get($queue, true);
        $channel->close();

        return $message;
    }

    /**
     * The number of messages ready for delivery in the queue, without those delivered and not yet acknowledged.
     */
    public function readyCount(string $queue): int
    {
        $channel = $this->channel();
        [, $ready] = $channel->queue_declare($queue, true) ?? [null, 0];
        $channel->close();

        return (int) $ready;
    }

    public function purge(string $queue): void
    {
        $channel = $this->channel();
        $channel->queue_purge($queue);
        $channel->close();
    }

    public function close(): void
    {
        $this->connection->close();
    }
}
