<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\RabbitMq;

use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Throwable;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommand;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommandHandler;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\TradeSignalMessage;

/**
 * Consumes trade signals from Trade Maneuver's intake queue with manual acknowledgement. A delivery is acknowledged
 * only after the intake use case has committed it. When the use case fails, for example because the database is
 * unavailable, the delivery is requeued and the failure ends consumption, so the process supervisor restarts it.
 */
final readonly class TradeSignalConsumer
{
    public const string INTAKE_QUEUE = 'tm.trade-signals.intake';

    private const int PREFETCH = 10;

    /** How long one wait for a delivery lasts before the consumer checks whether it should stop. */
    private const float WAIT_SECONDS = 1.0;

    public function __construct(
        private RabbitMqConnectionFactory $connections,
        private ReceiveTradeSignalCommandHandler $intake,
        private LoggerInterface $logger,
    ) {}

    /**
     * Consumes until `$shouldStop` returns true, which it is asked after every delivery and every second.
     *
     * @param  callable(): bool  $shouldStop
     */
    public function consume(callable $shouldStop): void
    {
        $connection = $this->connections->connect();
        try {
            $channel = $connection->channel();
            $channel->basic_qos(0, self::PREFETCH, false);
            $channel->basic_consume(self::INTAKE_QUEUE, '', false, false, false, false, $this->handle(...));
            while (! $shouldStop()) {
                try {
                    $channel->wait(null, false, self::WAIT_SECONDS);
                } catch (AMQPTimeoutException) {
                    // No delivery within the wait; check again whether to stop.
                }
            }
        } finally {
            // Closing the connection returns every unacknowledged delivery to the queue.
            $connection->close();
        }
    }

    private function handle(AMQPMessage $message): void
    {
        $publisherIdentity = $message->has('user_id') ? (string) $message->get('user_id') : null;
        $context = $this->contextOf($message, $publisherIdentity);
        try {
            $this->intake->handle(new ReceiveTradeSignalCommand($message->getBody(), $publisherIdentity));
        } catch (Throwable $exception) {
            $message->nack(true);
            $this->logger->error('A trade signal delivery failed before commit and is requeued.', [...$context, 'exception' => $exception]);

            throw $exception;
        }

        $message->ack();
        $this->logger->info('A trade signal delivery is acknowledged.', $context);
    }

    /**
     * @return array<string, string>
     */
    private function contextOf(AMQPMessage $message, ?string $publisherIdentity): array
    {
        return [
            'signalId' => TradeSignalMessage::fromBody($message->getBody())->metadata()['id'],
            'messageId' => $message->has('message_id') ? (string) $message->get('message_id') : '',
            'deliveryTag' => (string) $message->getDeliveryTag(),
            'publisherIdentity' => $publisherIdentity ?? '',
        ];
    }
}
