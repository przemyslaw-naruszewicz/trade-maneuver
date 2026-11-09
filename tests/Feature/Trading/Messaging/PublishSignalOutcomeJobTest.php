<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Messaging;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Exception\AMQPHeartbeatMissedException;
use RuntimeException;
use Tests\Support\Trading\AnsweringSignalOutcomePublisher;
use Tests\Support\Trading\RabbitMqTestClient;
use Tests\Support\Trading\TradeSignalMessageBuilder;
use Tests\TestCase;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommand;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommandHandler;
use TradeManeuver\Trading\Infrastructure\Messaging\Jobs\PublishSignalOutcomeJob;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\OutboxMessageDto;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\PublicationResult;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\SignalOutcomeNotPublishedException;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\SignalOutcomePublisherInterface;
use TradeManeuver\Trading\Infrastructure\Messaging\RabbitMq\RabbitMqSignalOutcomePublisher;

/**
 * Publication of outbox entries through Laravel's PostgreSQL job queue, against the database and the RabbitMQ of the
 * tests. The outbox entry comes from the intake use case, which rejects a trade signal with a confidence above 1 and
 * commits its signal outcome to the outbox. Each queue worker run processes at most one job.
 */
final class PublishSignalOutcomeJobTest extends TestCase
{
    use DatabaseTruncation;

    private RabbitMqTestClient $recipient;

    private SignalOutcomePublisherInterface $rabbitMqPublisher;

    protected function setUp(): void
    {
        parent::setUp();

        config(['trading.analytics_applications' => [['id' => 'demo-analytics', 'rabbitmq_user' => 'demo-analytics']]]);
        $this->travelTo('2026-11-09 10:00:00Z');
        Exceptions::fake();
        $this->rabbitMqPublisher = $this->app->make(SignalOutcomePublisherInterface::class);
        $this->recipient = RabbitMqTestClient::asProducer('demo-analytics');
        $this->recipient->purge(RabbitMqTestClient::outcomeQueueOf('demo-analytics'));
    }

    protected function tearDown(): void
    {
        $this->recipient->purge(RabbitMqTestClient::outcomeQueueOf('demo-analytics'));
        $this->recipient->close();
        DB::unprepared('TRUNCATE trading_inbox_entries, trading_trade_signals, trading_outbox_messages, trading_audit_records, jobs, failed_jobs');

        parent::tearDown();
    }

    public function test_an_outbox_entry_is_marked_sent_only_after_a_publisher_confirmation(): void
    {
        $this->commitASignalRejection();

        $this->publishWith(new AnsweringSignalOutcomePublisher(PublicationResult::Unconfirmed));

        $this->assertNull(DB::table('trading_outbox_messages')->sole()->sent_at, 'An entry without a publisher confirmation was expected to stay unsent.');
        Exceptions::assertReported(static fn (SignalOutcomeNotPublishedException $exception): bool => $exception->getMessage() === 'RabbitMQ did not confirm the signal outcome.');

        $this->travel(1)->hours();
        $this->publishWith($this->rabbitMqPublisher);

        $this->assertNotNull(DB::table('trading_outbox_messages')->sole()->sent_at);
        $this->assertNotNull($this->recipient->take(RabbitMqTestClient::outcomeQueueOf('demo-analytics')));
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_after_a_crash_between_commit_and_publication_the_entry_is_published_later_with_the_same_message_id(): void
    {
        $this->commitASignalRejection();
        $crashing = new class implements SignalOutcomePublisherInterface
        {
            public function publish(OutboxMessageDto $message, string $routingKey): PublicationResult
            {
                throw new RuntimeException('The queue worker crashed before publication.');
            }
        };
        $this->publishWith($crashing);

        $this->travel(1)->hours();
        $this->publishWith($this->rabbitMqPublisher);

        $entry = DB::table('trading_outbox_messages')->sole();
        $published = $this->recipient->take(RabbitMqTestClient::outcomeQueueOf('demo-analytics'));
        $this->assertNotNull($published, 'The committed entry was expected to be published after the crash.');
        $this->assertSame($entry->message_id, $published->get('message_id'));
        $this->assertSame($entry->message_id, json_decode($published->getBody(), true, 512, JSON_THROW_ON_ERROR)['id']);
        $this->assertNotNull($entry->sent_at);
    }

    public function test_a_signal_outcome_rabbitmq_cannot_route_stays_unsent_and_is_reported_as_an_error(): void
    {
        config(['trading.analytics_applications' => [['id' => 'demo-unbound', 'rabbitmq_user' => 'demo-unbound']]]);
        $this->commitASignalRejection('demo-unbound');

        $this->publishWith($this->rabbitMqPublisher);

        $entry = DB::table('trading_outbox_messages')->sole();
        $this->assertNull($entry->sent_at);
        Exceptions::assertReported(static fn (SignalOutcomeNotPublishedException $exception): bool => $exception->getMessage() === 'RabbitMQ cannot route the signal outcome to an outcome queue.'
            && $exception->context() === [
                'messageId' => $entry->message_id,
                'signalId' => TradeSignalMessageBuilder::idFor('purchase'),
                'recipient' => 'demo-unbound',
            ]);
    }

    public function test_an_entry_for_a_recipient_no_longer_registered_stays_unsent_and_is_reported_as_an_error(): void
    {
        $this->commitASignalRejection();
        config(['trading.analytics_applications' => []]);

        $this->publishWith(new AnsweringSignalOutcomePublisher(PublicationResult::Confirmed));

        $this->assertNull(DB::table('trading_outbox_messages')->sole()->sent_at);
        Exceptions::assertReported(static fn (SignalOutcomeNotPublishedException $exception): bool => $exception->getMessage() === 'The recipient is not a known analytics application.');
    }

    public function test_retries_wait_twice_as_long_after_each_failed_attempt_and_the_tenth_failure_keeps_the_job_in_failed_jobs(): void
    {
        $this->commitASignalRejection();
        $publisher = new AnsweringSignalOutcomePublisher(PublicationResult::Returned);

        foreach ([1, 2, 4, 8, 16, 32, 64, 128, 256] as $attempt => $delay) {
            $this->publishWith($publisher);
            $job = DB::table('jobs')->sole();
            $this->assertSame($attempt + 1, $job->attempts);
            $this->assertSame(now()->getTimestamp() + $delay, $job->available_at, "The retry after attempt {$job->attempts} was expected to wait {$delay} s.");

            $this->travel($delay - 1)->seconds();
            $this->publishWith($publisher);
            $this->assertSame($attempt + 1, DB::table('jobs')->sole()->attempts, 'A job that is not due yet was expected to wait.');
            $this->travel(1)->seconds();
        }
        $this->publishWith($publisher);

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertStringContainsString('PublishSignalOutcomeJob', DB::table('failed_jobs')->sole()->payload);
        $this->assertNull(DB::table('trading_outbox_messages')->sole()->sent_at);
        Exceptions::assertReported(SignalOutcomeNotPublishedException::class);
    }

    public function test_a_published_entry_is_marked_sent_at_the_current_time_logged_and_not_published_again(): void
    {
        $this->commitASignalRejection();
        $log = Log::spy();
        $publisher = new AnsweringSignalOutcomePublisher(PublicationResult::Confirmed);

        $this->publishWith($publisher);

        $entry = DB::table('trading_outbox_messages')->sole();
        $this->assertSame('2026-11-09 10:00:00+00', $entry->sent_at);
        $log->shouldHaveReceived('info')->once()->withArgs(
            static fn (string $message, array $context): bool => $message === 'A signal outcome is published.'
                && $context === [
                    'messageId' => $entry->message_id,
                    'signalId' => TradeSignalMessageBuilder::idFor('purchase'),
                    'recipient' => 'demo-analytics',
                    'attempt' => 1,
                ],
        );

        $this->dispatchAJobFor($entry->id);
        $failing = new AnsweringSignalOutcomePublisher(PublicationResult::Unconfirmed);
        $this->publishWith($failing);

        $this->assertSame(0, DB::table('jobs')->count(), 'A job for an entry already sent was expected to finish without publishing.');
        Exceptions::assertNothingReported();
    }

    public function test_a_signal_outcome_is_published_as_a_persistent_json_message_of_its_type(): void
    {
        $this->commitASignalRejection();

        $this->publishWith($this->rabbitMqPublisher);

        $published = $this->recipient->take(RabbitMqTestClient::outcomeQueueOf('demo-analytics'));
        $this->assertNotNull($published);
        $this->assertSame('application/json', $published->get('content_type'));
        $this->assertSame(2, $published->get('delivery_mode'));
        $this->assertSame('tm.signal-outcome.v1', $published->get('type'));
        $this->assertSame('SignalRejected', json_decode($published->getBody(), true, 512, JSON_THROW_ON_ERROR)['outcome']);
    }

    public function test_after_rabbitmq_drops_the_connection_a_later_attempt_publishes_on_a_new_connection(): void
    {
        // The publisher sends no heartbeats while it is idle, so after a few seconds RabbitMQ closes the connection and
        // php-amqplib refuses to write to it, as after a RabbitMQ restart.
        $connectionConfig = $this->app->make(AMQPConnectionConfig::class);
        $connectionConfig->setHeartbeat(1);
        $publisher = new RabbitMqSignalOutcomePublisher($connectionConfig);
        $this->commitASignalRejection();
        $this->publishWith($publisher);
        $this->assertNotNull($this->recipient->take(RabbitMqTestClient::outcomeQueueOf('demo-analytics')));

        sleep(4);
        $this->commitASignalRejection(alias: 'second purchase');
        $this->publishWith($publisher);
        Exceptions::assertReported(AMQPHeartbeatMissedException::class);
        $this->travel(1)->hours();
        $this->publishWith($publisher);

        $this->assertSame(0, DB::table('trading_outbox_messages')->whereNull('sent_at')->count(), 'The entry was expected to be published after the lost connection.');
        $published = $this->recipient->take(RabbitMqTestClient::outcomeQueueOf('demo-analytics'));
        $this->assertNotNull($published);
        $this->assertSame(TradeSignalMessageBuilder::idFor('second purchase'), json_decode($published->getBody(), true, 512, JSON_THROW_ON_ERROR)['signalId']);
    }

    public function test_the_publication_job_is_queued_in_the_use_case_transaction(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION trading_test_fail_job_write() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'job write failed for the test with % outbox entries written', (SELECT count(*) FROM trading_outbox_messages);
            END
            $$;
            CREATE TRIGGER trading_test_fail_job_write BEFORE INSERT ON jobs FOR EACH ROW EXECUTE FUNCTION trading_test_fail_job_write();
            SQL);

        try {
            $this->commitASignalRejection();
            $this->fail('The job write was expected to fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('job write failed for the test with 1 outbox entries written', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER trading_test_fail_job_write ON jobs; DROP FUNCTION trading_test_fail_job_write();');
        }

        $this->assertSame(0, DB::transactionLevel());
        $this->assertDatabaseEmpty('trading_outbox_messages');
        $this->assertDatabaseEmpty('trading_trade_signals');
    }

    private function commitASignalRejection(string $source = 'demo-analytics', string $alias = 'purchase'): void
    {
        $body = TradeSignalMessageBuilder::aTradeSignal(TradeSignalMessageBuilder::idFor($alias), $source)->with('confidence', '1.5')->body();
        $this->app->make(ReceiveTradeSignalCommandHandler::class)->handle(new ReceiveTradeSignalCommand($body, $source));
    }

    private function dispatchAJobFor(int $outboxMessageId): void
    {
        dispatch(new PublishSignalOutcomeJob($outboxMessageId));
    }

    /**
     * Runs one queue worker iteration, which publishes with the given publisher.
     */
    private function publishWith(SignalOutcomePublisherInterface $publisher): void
    {
        $this->app->instance(SignalOutcomePublisherInterface::class, $publisher);
        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0]);
    }
}
