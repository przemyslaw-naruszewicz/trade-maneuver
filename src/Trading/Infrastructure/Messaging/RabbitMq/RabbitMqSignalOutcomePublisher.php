<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\RabbitMq;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Exception\AMQPExceptionInterface;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\OutboxMessageDto;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\PublicationResult;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\SignalOutcomePublisherInterface;

/**
 * Publishes persistent, mandatory messages to tm.signal-outcomes on a channel in confirm mode, and waits for each
 * confirmation. RabbitMQ returns a mandatory message it cannot route before confirming it, so a return wins over the
 * confirmation that follows it. A refusal leaves the result unconfirmed, like a confirmation that never arrives.
 * After an AMQP failure, such as a connection RabbitMQ dropped, the next publication opens a new connection.
 */
final class RabbitMqSignalOutcomePublisher implements SignalOutcomePublisherInterface
{
    public const string OUTCOME_EXCHANGE = 'tm.signal-outcomes';

    private const float CONFIRMATION_TIMEOUT_SECONDS = 5.0;

    private ?AbstractConnection $connection = null;

    private ?AMQPChannel $channel = null;

    private ?PublicationResult $result = null;

    public function __construct(private readonly AMQPConnectionConfig $connectionConfig) {}

    public function publish(OutboxMessageDto $message, string $routingKey): PublicationResult
    {
        try {
            return $this->publishOnChannel($message, $routingKey);
        } catch (AMQPExceptionInterface $exception) {
            $this->forgetConnection();

            throw $exception;
        }
    }

    private function publishOnChannel(OutboxMessageDto $message, string $routingKey): PublicationResult
    {
        $channel = $this->channel();
        $this->result = null;
        $channel->basic_publish(
            new AMQPMessage($message->payload, [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => $message->messageId,
                'type' => $message->messageType,
            ]),
            self::OUTCOME_EXCHANGE,
            $routingKey,
            true,
        );

        try {
            $channel->wait_for_pending_acks_returns(self::CONFIRMATION_TIMEOUT_SECONDS);
        } catch (AMQPTimeoutException) {
            // A late confirmation would belong to this message, so the next one starts on a new connection.
            $this->connection?->close();
            $this->forgetConnection();

            return PublicationResult::Unconfirmed;
        }

        return $this->result ?? PublicationResult::Unconfirmed;
    }

    private function channel(): AMQPChannel
    {
        if ($this->channel !== null) {
            return $this->channel;
        }
        $this->connection = AMQPConnectionFactory::create($this->connectionConfig);
        $channel = $this->connection->channel();
        $channel->confirm_select();
        $channel->set_return_listener(function (): void {
            $this->result = PublicationResult::Returned;
        });
        $channel->set_ack_handler(function (): void {
            $this->result ??= PublicationResult::Confirmed;
        });

        return $this->channel = $channel;
    }

    /**
     * PHP destroys the forgotten connection later, and php-amqplib then closes it, ignoring a socket that is already gone.
     */
    private function forgetConnection(): void
    {
        $this->connection = null;
        $this->channel = null;
    }
}
