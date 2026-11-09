<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\RabbitMq;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\OutboxMessageDto;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\PublicationResult;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\SignalOutcomePublisherInterface;

/**
 * Publishes persistent, mandatory messages to tm.signal-outcomes on a channel in confirm mode, and waits for each
 * confirmation. RabbitMQ returns a mandatory message it cannot route before confirming it, so a return wins over the
 * confirmation that follows it.
 */
final class RabbitMqSignalOutcomePublisher implements SignalOutcomePublisherInterface
{
    public const string OUTCOME_EXCHANGE = 'tm.signal-outcomes';

    private const float CONFIRMATION_TIMEOUT_SECONDS = 5.0;

    private ?AbstractConnection $connection = null;

    private ?AMQPChannel $channel = null;

    private ?PublicationResult $result = null;

    public function __construct(private readonly RabbitMqConnectionFactory $connections) {}

    public function publish(OutboxMessageDto $message, string $routingKey): PublicationResult
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
            $this->disconnect();

            return PublicationResult::Unconfirmed;
        }

        return $this->result ?? PublicationResult::Unconfirmed;
    }

    private function channel(): AMQPChannel
    {
        if ($this->channel !== null && $this->channel->is_open()) {
            return $this->channel;
        }
        $this->connection = $this->connections->connect();
        $channel = $this->connection->channel();
        $channel->confirm_select();
        $channel->set_return_listener(function (): void {
            $this->result = PublicationResult::Returned;
        });
        $channel->set_ack_handler(function (): void {
            $this->result ??= PublicationResult::Confirmed;
        });
        $channel->set_nack_handler(function (): void {
            $this->result ??= PublicationResult::Refused;
        });

        return $this->channel = $channel;
    }

    private function disconnect(): void
    {
        $this->connection?->close();
        $this->connection = null;
        $this->channel = null;
    }
}
