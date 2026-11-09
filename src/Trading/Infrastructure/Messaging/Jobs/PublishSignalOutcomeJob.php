<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use TradeManeuver\Trading\Application\Port\AnalyticsApplicationRegistryInterface;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\PostgresOutbox;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\PublicationResult;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\SignalOutcomeNotPublishedException;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\SignalOutcomePublisherInterface;

/**
 * Publishes one outbox entry to the outcome queue of its recipient. The job is queued on the `database` connection in
 * the use-case transaction that writes the entry, so it commits or rolls back with it, and a crash after the commit
 * leaves the job to run later. The entry is marked sent only after RabbitMQ confirms the message. Any other result
 * fails the attempt; the queue retries it with backoff and, after the last attempt, keeps it in failed_jobs.
 */
final class PublishSignalOutcomeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public function __construct(public readonly int $outboxMessageId)
    {
        $this->onConnection('database');
    }

    /**
     * The seconds to wait before each retry.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [1, 2, 4, 8, 16, 32, 64, 128, 256];
    }

    public function handle(
        PostgresOutbox $outbox,
        SignalOutcomePublisherInterface $publisher,
        AnalyticsApplicationRegistryInterface $analyticsApplicationRegistry,
        ClockInterface $clock,
        LoggerInterface $logger,
    ): void {
        $message = $outbox->findUnsent($this->outboxMessageId);
        if ($message === null) {
            // An earlier attempt published the entry and marked it sent.
            return;
        }

        $routingKey = $analyticsApplicationRegistry->outcomeRoutingKey(new AnalyticsApplicationId($message->recipient))
            ?? throw new SignalOutcomeNotPublishedException($message, 'The recipient is not a known analytics application.');
        $result = $publisher->publish($message, $routingKey);
        if ($result !== PublicationResult::Confirmed) {
            throw new SignalOutcomeNotPublishedException($message, $result === PublicationResult::Returned
                ? 'RabbitMQ cannot route the signal outcome to an outcome queue.'
                : 'RabbitMQ did not confirm the signal outcome.');
        }

        $outbox->markSent($message, $clock->now());
        $logger->info('A signal outcome is published.', [
            'messageId' => $message->messageId,
            'signalId' => $message->signalId,
            'recipient' => $message->recipient,
            'attempt' => $this->attempts(),
        ]);
    }
}
