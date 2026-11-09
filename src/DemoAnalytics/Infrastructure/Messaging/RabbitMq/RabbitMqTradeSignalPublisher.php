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

    private const string CONFIRMED = 'confirmed';

    private const string UNCONFIRMED = 'did not confirm';

    public function __construct(private AMQPConnectionConfig $connectionConfig) {}

    public function publish(string $messageId, string $body): void
    {
        try {
            $outcome = $this->publishOnNewConnection($messageId, $body);
        } catch (AMQPExceptionInterface $exception) {
            throw new TradeSignalNotPublishedException($exception->getMessage(), previous: $exception);
        }

        if ($outcome !== self::CONFIRMED) {
            throw new TradeSignalNotPublishedException(sprintf('RabbitMQ %s the trade signal.', $outcome));
        }
    }

    /**
     * The connection's destructor would close it as well, so a test cannot tell whether close() runs.
     *
     * @infection-ignore-all
     */
    private function publishOnNewConnection(string $messageId, string $body): string
    {
        $connection = AMQPConnectionFactory::create($this->connectionConfig);
        try {
            return $this->publishOn($connection->channel(), $messageId, $body);
        } finally {
            $connection->close();
        }
    }

    /**
     * Returns "confirmed", or how RabbitMQ failed to accept the message.
     */
    private function publishOn(AMQPChannel $channel, string $messageId, string $body): string
    {
        $outcome = self::UNCONFIRMED;
        $channel->confirm_select();
        $channel->set_ack_handler(function () use (&$outcome): void {
            $outcome = $outcome === self::UNCONFIRMED ? self::CONFIRMED : $outcome;
        });
        $this->publishMandatory($channel, $outcome, new AMQPMessage($body, [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'message_id' => $messageId,
            'type' => 'tm.trade-signal.v1',
            'user_id' => $this->connectionConfig->getUser(),
        ]));
        $channel->wait_for_pending_acks_returns(self::CONFIRMATION_TIMEOUT_SECONDS);

        return $outcome;
    }

    /**
     * Publishes the message as mandatory and records a return or a refusal in `$outcome`. RabbitMQ returns a mandatory
     * message before it confirms it, so a return wins over the confirmation. RabbitMQ returns or refuses a message to
     * tm.trade-signals only when its topology is broken or the node fails, which no test can bring about.
     *
     * @infection-ignore-all
     */
    private function publishMandatory(AMQPChannel $channel, string &$outcome, AMQPMessage $message): void
    {
        $channel->set_return_listener(function () use (&$outcome): void {
            $outcome = 'could not route';
        });
        $channel->set_nack_handler(function () use (&$outcome): void {
            $outcome = 'refused';
        });
        $channel->basic_publish($message, self::EXCHANGE, '', true);
    }
}
