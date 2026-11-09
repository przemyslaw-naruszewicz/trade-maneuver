<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Messaging;

use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Clock\MockClock;
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

    private function commitASignalRejection(): void
    {
        $body = TradeSignalMessageBuilder::aTradeSignal(TradeSignalMessageBuilder::idFor('purchase'))->with('confidence', '1.5')->body();
        $this->app->make(ReceiveTradeSignalCommandHandler::class)->handle(new ReceiveTradeSignalCommand($body, 'demo-analytics'));
    }

    private function dispatcherPublishingWith(SignalOutcomePublisherInterface $publisher): OutboxDispatcher
    {
        return $this->app->make(OutboxDispatcher::class, ['publisher' => $publisher, 'clock' => $this->clock]);
    }

    private function publisherAnswering(PublicationResult $result): SignalOutcomePublisherInterface
    {
        return new readonly class($result) implements SignalOutcomePublisherInterface
        {
            public function __construct(private PublicationResult $result) {}

            public function publish(OutboxMessageDto $message, string $routingKey): PublicationResult
            {
                return $this->result;
            }
        };
    }
}
