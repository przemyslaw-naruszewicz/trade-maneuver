<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Infrastructure\Messaging\RabbitMq;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Exception\AMQPExceptionInterface;
use PhpAmqpLib\Message\AMQPMessage;
use TradeManeuver\DemoAnalytics\Application\Port\TradeSignalPublisherInterface;
use TradeManeuver\DemoAnalytics\Application\TradeSignal\Exception\TradeSignalNotPublishedException;

/**
 * Publishes each trade signal on its own connection, as a persistent, mandatory message to tm.trade-signals on a
 * channel in confirm mode. The AMQP `user_id` is the producer user the connection logs in as, which proves the
 * publisher to Trade Maneuver.
 */
final readonly class RabbitMqTradeSignalPublisher implements TradeSignalPublisherInterface
{
    public const string EXCHANGE = 'tm.trade-signals';

    private const float CONFIRMATION_TIMEOUT_SECONDS = 5.0;

    public function __construct(private AMQPConnectionConfig $connectionConfig) {}

    public function publish(string $messageId, string $body): void
    {
        try {
            $connection = AMQPConnectionFactory::create($this->connectionConfig);
        } catch (AMQPExceptionInterface $exception) {
            throw new TradeSignalNotPublishedException($exception->getMessage(), 0, $exception);
        }

        try {
            $outcome = $this->publishOn($connection->channel(), $messageId, $body);
        } catch (AMQPExceptionInterface $exception) {
            throw new TradeSignalNotPublishedException($exception->getMessage(), 0, $exception);
        } finally {
            $connection->close();
        }

        if ($outcome !== 'confirmed') {
            throw new TradeSignalNotPublishedException(sprintf('RabbitMQ %s the trade signal.', $outcome));
        }
    }

    /**
     * Returns "confirmed", or how RabbitMQ refused the message. A returned message is also confirmed, so a return wins.
     */
    private function publishOn(AMQPChannel $channel, string $messageId, string $body): string
    {
        $outcome = 'did not confirm';
        $channel->confirm_select();
        $channel->set_return_listener(function () use (&$outcome): void {
            $outcome = 'could not route';
        });
        $channel->set_nack_handler(function () use (&$outcome): void {
            $outcome = 'refused';
        });
        $channel->set_ack_handler(function () use (&$outcome): void {
            $outcome = $outcome === 'did not confirm' ? 'confirmed' : $outcome;
        });
        $channel->basic_publish(
            new AMQPMessage($body, [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => $messageId,
                'type' => 'tm.trade-signal.v1',
                'user_id' => $this->connectionConfig->getUser(),
            ]),
            self::EXCHANGE,
            '',
            true,
        );
        $channel->wait_for_pending_acks_returns(self::CONFIRMATION_TIMEOUT_SECONDS);

        return $outcome;
    }
}
