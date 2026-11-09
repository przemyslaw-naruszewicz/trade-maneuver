<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\RabbitMq;

use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommand;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommandHandler;

/**
 * Consumes trade signals from Trade Maneuver's intake queue and passes each one to the intake use case.
 */
final readonly class TradeSignalConsumer
{
    public const string INTAKE_QUEUE = 'tm.trade-signals.intake';

    public function __construct(
        private RabbitMqConnectionFactory $connections,
        private ReceiveTradeSignalCommandHandler $intake,
    ) {}

    /**
     * @param  callable(): bool  $shouldStop
     */
    public function consume(callable $shouldStop): void
    {
        $connection = $this->connections->connect();
        $channel = $connection->channel();
        $channel->basic_consume(self::INTAKE_QUEUE, '', false, false, false, false, $this->handle(...));
        while (! $shouldStop()) {
            try {
                $channel->wait(null, false, 1.0);
            } catch (AMQPTimeoutException) {
            }
        }
        $connection->close();
    }

    private function handle(AMQPMessage $message): void
    {
        $message->ack();
        $publisherIdentity = $message->has('user_id') ? (string) $message->get('user_id') : null;
        $this->intake->handle(new ReceiveTradeSignalCommand($message->getBody(), $publisherIdentity));
    }
}
