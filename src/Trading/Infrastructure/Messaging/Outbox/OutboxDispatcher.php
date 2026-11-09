<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\Outbox;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use TradeManeuver\Trading\Application\Port\AnalyticsApplicationRegistryInterface;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;

/**
 * Publishes committed outbox entries to the outcome queue of their recipient. An entry is marked sent only after the
 * message broker confirms it; any other result leaves it unsent and schedules the next attempt with exponential
 * backoff. A crash before an entry is marked sent leaves it due, so it is published again with the same message id.
 */
final readonly class OutboxDispatcher
{
    private const int BATCH_SIZE = 50;

    private const int FIRST_RETRY_DELAY_SECONDS = 1;

    private const int MAX_RETRY_DELAY_SECONDS = 300;

    public function __construct(
        private PostgresOutbox $outbox,
        private SignalOutcomePublisherInterface $publisher,
        private AnalyticsApplicationRegistryInterface $analyticsApplicationRegistry,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    /**
     * Attempts every entry that is due and returns how many of them were sent.
     */
    public function dispatchDue(): int
    {
        $sent = 0;
        foreach ($this->outbox->due($this->clock->now(), self::BATCH_SIZE) as $message) {
            if ($this->dispatch($message)) {
                $sent++;
            }
        }

        return $sent;
    }

    private function dispatch(OutboxMessageDto $message): bool
    {
        $routingKey = $this->analyticsApplicationRegistry->outcomeRoutingKey(new AnalyticsApplicationId($message->recipient));
        if ($routingKey === null) {
            $this->fail($message, 'The recipient is not a known analytics application.', isError: true);

            return false;
        }

        $result = $this->publisher->publish($message, $routingKey);
        match ($result) {
            PublicationResult::Confirmed => $this->markSent($message),
            PublicationResult::Returned => $this->fail($message, 'RabbitMQ cannot route the signal outcome to an outcome queue.', isError: true),
            PublicationResult::Refused => $this->fail($message, 'RabbitMQ refused the signal outcome.', isError: false),
            PublicationResult::Unconfirmed => $this->fail($message, 'RabbitMQ did not confirm the signal outcome in time.', isError: false),
        };

        return $result === PublicationResult::Confirmed;
    }

    private function markSent(OutboxMessageDto $message): void
    {
        $this->outbox->markSent($message, $this->clock->now());
        $this->logger->info('A signal outcome is published.', $this->contextOf($message));
    }

    private function fail(OutboxMessageDto $message, string $error, bool $isError): void
    {
        $delaySeconds = min(self::MAX_RETRY_DELAY_SECONDS, self::FIRST_RETRY_DELAY_SECONDS * 2 ** min($message->attempts, 16));
        $nextAttemptAt = $this->clock->now()->modify(sprintf('+%d seconds', $delaySeconds));
        $this->outbox->recordFailedAttempt($message, $error, $nextAttemptAt);

        $context = [...$this->contextOf($message), 'error' => $error, 'nextAttemptAt' => $nextAttemptAt->format(DATE_ATOM)];
        if ($isError) {
            $this->logger->error('A signal outcome stays unsent.', $context);
        } else {
            $this->logger->warning('A signal outcome stays unsent and will be retried.', $context);
        }
    }

    /**
     * @return array<string, string|int>
     */
    private function contextOf(OutboxMessageDto $message): array
    {
        $payload = json_decode($message->payload, true);

        return [
            'messageId' => $message->messageId,
            'signalId' => is_array($payload) && is_string($payload['signalId'] ?? null) ? $payload['signalId'] : '',
            'recipient' => $message->recipient,
            'attempts' => $message->attempts + 1,
        ];
    }
}
