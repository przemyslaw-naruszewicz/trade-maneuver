<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Messaging;

use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Tests\Support\Trading\AnsweringSignalOutcomePublisher;
use Tests\Support\Trading\RabbitMqTestClient;
use Tests\Support\Trading\TradeSignalMessageBuilder;
use Tests\TestCase;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommand;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommandHandler;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\OutboxDispatcher;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\OutboxMessageDto;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\PublicationResult;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\SignalOutcomePublisherInterface;

/**
 * The outbox dispatcher against the database and the RabbitMQ of the tests. The outbox entry comes from the intake
 * use case, which rejects a trade signal with a confidence above 1 and commits its signal outcome to the outbox.
 */
final class OutboxDispatcherTest extends TestCase
{
    use DatabaseTruncation;

    private RabbitMqTestClient $recipient;

    private MockClock $clock;

    protected function setUp(): void
    {
        parent::setUp();

        config(['trading.analytics_applications' => [['id' => 'demo-analytics', 'rabbitmq_user' => 'demo-analytics']]]);
        $this->clock = new MockClock(new DateTimeImmutable('2026-11-09T10:00:00Z'));
        $this->recipient = RabbitMqTestClient::asProducer('demo-analytics');
        $this->recipient->purge(RabbitMqTestClient::outcomeQueueOf('demo-analytics'));
    }

    protected function tearDown(): void
    {
        $this->recipient->purge(RabbitMqTestClient::outcomeQueueOf('demo-analytics'));
        $this->recipient->close();
        DB::unprepared('TRUNCATE trading_inbox_entries, trading_trade_signals, trading_outbox_messages, trading_audit_records');

        parent::tearDown();
    }

    public function test_an_outbox_entry_is_marked_sent_only_after_a_publisher_confirmation(): void
    {
        $this->commitASignalRejection();

        $this->dispatcherPublishingWith($this->publisherAnswering(PublicationResult::Unconfirmed))->dispatchDue();

        $entry = DB::table('trading_outbox_messages')->sole();
        $this->assertNull($entry->sent_at, 'An entry without a publisher confirmation was expected to stay unsent.');
        $this->assertSame(1, $entry->attempts);

        $this->clock->modify('+1 hour');
        $this->dispatcherPublishingWith($this->app->make(SignalOutcomePublisherInterface::class))->dispatchDue();

        $this->assertNotNull(DB::table('trading_outbox_messages')->sole()->sent_at);
        $this->assertNotNull($this->recipient->take(RabbitMqTestClient::outcomeQueueOf('demo-analytics')));
    }

    public function test_after_a_crash_between_commit_and_publication_the_entry_is_published_later_with_the_same_message_id(): void
    {
        $this->commitASignalRejection();
        $crashing = new class implements SignalOutcomePublisherInterface
        {
            public function publish(OutboxMessageDto $message, string $routingKey): PublicationResult
            {
                throw new RuntimeException('The dispatcher process crashed before publication.');
            }
        };
        try {
            $this->dispatcherPublishingWith($crashing)->dispatchDue();
        } catch (RuntimeException) {
            // The crash ends the dispatcher run.
        }

        $this->dispatcherPublishingWith($this->app->make(SignalOutcomePublisherInterface::class))->dispatchDue();

        $entry = DB::table('trading_outbox_messages')->sole();
        $published = $this->recipient->take(RabbitMqTestClient::outcomeQueueOf('demo-analytics'));
        $this->assertNotNull($published, 'The committed entry was expected to be published after the crash.');
        $this->assertSame($entry->message_id, $published->get('message_id'));
        $this->assertSame($entry->message_id, json_decode($published->getBody(), true, 512, JSON_THROW_ON_ERROR)['id']);
        $this->assertNotNull($entry->sent_at);
    }

    public function test_a_signal_outcome_rabbitmq_cannot_route_stays_unsent_and_is_logged_as_an_error(): void
    {
        config(['trading.analytics_applications' => [['id' => 'demo-unbound', 'rabbitmq_user' => 'demo-unbound']]]);
        $this->commitASignalRejection('demo-unbound');
        $log = Log::spy();

        $sent = $this->dispatcherPublishingWith($this->app->make(SignalOutcomePublisherInterface::class))->dispatchDue();

        $entry = DB::table('trading_outbox_messages')->sole();
        $this->assertSame(0, $sent);
        $this->assertNull($entry->sent_at);
        $this->assertSame('RabbitMQ cannot route the signal outcome to an outcome queue.', $entry->last_error);
        $log->shouldHaveReceived('error')->once()->withArgs(
            static fn (string $message, array $context): bool => $message === 'A signal outcome stays unsent.'
                && $context['messageId'] === $entry->message_id
                && $context['signalId'] === TradeSignalMessageBuilder::idFor('purchase')
                && $context['recipient'] === 'demo-unbound',
        );
    }

    public function test_an_entry_for_a_recipient_no_longer_registered_stays_unsent_and_is_logged_as_an_error(): void
    {
        $this->commitASignalRejection();
        config(['trading.analytics_applications' => []]);
        $log = Log::spy();

        $this->dispatcherPublishingWith($this->publisherAnswering(PublicationResult::Confirmed))->dispatchDue();

        $entry = DB::table('trading_outbox_messages')->sole();
        $this->assertNull($entry->sent_at);
        $this->assertSame('The recipient is not a known analytics application.', $entry->last_error);
        $log->shouldHaveReceived('error')->once();
    }

    public function test_retries_wait_twice_as_long_after_each_failed_attempt_up_to_five_minutes(): void
    {
        $this->commitASignalRejection();
        $log = Log::spy();

        $dispatcher = $this->dispatcherPublishingWith($this->publisherAnswering(PublicationResult::Refused));
        $dispatcher->dispatchDue();
        $this->assertNextAttemptAt('2026-11-09 10:00:01+00', 1);
        $dispatcher->dispatchDue();
        $this->assertNextAttemptAt('2026-11-09 10:00:01+00', 1, 'An entry that is not due yet was expected to wait.');

        $this->clock->modify('+1 second');
        $dispatcher->dispatchDue();
        $this->assertNextAttemptAt('2026-11-09 10:00:03+00', 2);

        DB::table('trading_outbox_messages')->update(['attempts' => 8, 'next_attempt_at' => null]);
        $dispatcher->dispatchDue();
        $this->assertNextAttemptAt('2026-11-09 10:04:17+00', 9);

        DB::table('trading_outbox_messages')->update(['attempts' => 40, 'next_attempt_at' => null]);
        $dispatcher->dispatchDue();
        $this->assertNextAttemptAt('2026-11-09 10:05:01+00', 41);
        $this->assertSame('RabbitMQ refused the signal outcome.', DB::table('trading_outbox_messages')->sole()->last_error);
        $log->shouldHaveReceived('warning')->times(4);
        $log->shouldNotHaveReceived('error');
    }

    public function test_dispatching_returns_how_many_entries_were_sent(): void
    {
        $this->commitASignalRejection();

        $dispatcher = $this->dispatcherPublishingWith($this->publisherAnswering(PublicationResult::Confirmed));

        $this->assertSame(1, $dispatcher->dispatchDue());
        $this->assertSame(0, $dispatcher->dispatchDue());
        $this->assertSame('2026-11-09 10:00:00+00', DB::table('trading_outbox_messages')->sole()->sent_at);
    }

    private function assertNextAttemptAt(string $expected, int $attempts, string $message = ''): void
    {
        $entry = DB::table('trading_outbox_messages')->sole();
        $this->assertSame($expected, $entry->next_attempt_at, $message);
        $this->assertSame($attempts, $entry->attempts, $message);
        $this->assertNull($entry->sent_at);
    }

    private function commitASignalRejection(string $source = 'demo-analytics'): void
    {
        $body = TradeSignalMessageBuilder::aTradeSignal(TradeSignalMessageBuilder::idFor('purchase'), $source)->with('confidence', '1.5')->body();
        $this->app->make(ReceiveTradeSignalCommandHandler::class)->handle(new ReceiveTradeSignalCommand($body, $source));
    }

    private function dispatcherPublishingWith(SignalOutcomePublisherInterface $publisher): OutboxDispatcher
    {
        return $this->app->make(OutboxDispatcher::class, ['publisher' => $publisher, 'clock' => $this->clock]);
    }

    private function publisherAnswering(PublicationResult $result): SignalOutcomePublisherInterface
    {
        return new AnsweringSignalOutcomePublisher($result);
    }
}
