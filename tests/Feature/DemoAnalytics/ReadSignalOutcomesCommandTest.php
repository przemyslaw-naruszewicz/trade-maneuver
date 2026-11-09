<?php

declare(strict_types=1);

namespace Tests\Feature\DemoAnalytics;

use Illuminate\Support\Facades\Artisan;
use PhpAmqpLib\Message\AMQPMessage;
use Tests\Support\Trading\RabbitMqTestClient;
use Tests\TestCase;

/**
 * The reading command against the demo analytics application's outcome queue in the RabbitMQ test vhost. The tests
 * publish the outcome messages themselves as Trade Maneuver.
 */
final class ReadSignalOutcomesCommandTest extends TestCase
{
    private const string COMMAND = 'demo-analytics:read-signal-outcomes';

    private const string OUTCOME_QUEUE = 'tm.signal-outcomes.demo-analytics';

    private RabbitMqTestClient $producer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = RabbitMqTestClient::asProducer('demo-analytics');
        $this->producer->purge(self::OUTCOME_QUEUE);
    }

    protected function tearDown(): void
    {
        $this->producer->purge(self::OUTCOME_QUEUE);
        $this->producer->close();

        parent::tearDown();
    }

    public function test_each_signal_outcome_is_printed_with_its_details_and_acknowledged(): void
    {
        $this->publishOutcome([
            'signalId' => '019a5a6e-0000-7000-8000-0000000000a1',
            'outcome' => 'SignalDeclined',
            'reason' => 'InstrumentUnavailable',
        ]);
        $this->publishOutcome([
            'signalId' => '019a5a6e-0000-7000-8000-0000000000a2',
            'outcome' => 'OrderFilled',
            'executedQuantity' => 16,
        ]);
        $this->publishOutcome([
            'signalId' => '019a5a6e-0000-7000-8000-0000000000a3',
            'outcome' => 'SignalRejected',
            'violations' => [
                ['field' => 'confidence', 'message' => 'The confidence must be a decimal string from 0 to 1, such as 0.85.'],
                ['field' => 'instrument.mic', 'message' => 'The exchange code must have the MIC format: four upper-case letters or digits.'],
            ],
        ]);

        $exitCode = Artisan::call(self::COMMAND);

        $this->assertSame(0, $exitCode);
        $this->assertSame(
            "Trade signal 019a5a6e-0000-7000-8000-0000000000a1: SignalDeclined\n"
            ."  reason: InstrumentUnavailable\n"
            ."Trade signal 019a5a6e-0000-7000-8000-0000000000a2: OrderFilled\n"
            ."  executed quantity: 16\n"
            ."Trade signal 019a5a6e-0000-7000-8000-0000000000a3: SignalRejected\n"
            ."  confidence: The confidence must be a decimal string from 0 to 1, such as 0.85.\n"
            ."  instrument.mic: The exchange code must have the MIC format: four upper-case letters or digits.\n",
            Artisan::output(),
        );
        $this->assertSame(0, $this->producer->readyCount(self::OUTCOME_QUEUE), 'The printed signal outcomes were expected to be acknowledged.');
    }

    public function test_an_empty_outcome_queue_is_reported(): void
    {
        $exitCode = Artisan::call(self::COMMAND);

        $this->assertSame(0, $exitCode);
        $this->assertSame("The outcome queue tm.signal-outcomes.demo-analytics is empty.\n", Artisan::output());
    }

    public function test_an_outcome_message_that_cannot_be_read_is_reported_and_stays_in_the_queue(): void
    {
        $this->publishOutcome(['signalId' => '019a5a6e-0000-7000-8000-0000000000a1']);

        $exitCode = Artisan::call(self::COMMAND);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('A signal outcome could not be read: the outcome must be a string.', Artisan::output());
        $this->assertSame(1, $this->producer->readyCount(self::OUTCOME_QUEUE), 'The unread outcome message was expected to stay in the queue.');
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function publishOutcome(array $fields): void
    {
        $tradeManeuver = RabbitMqTestClient::asTradeManeuver();
        $channel = $tradeManeuver->channel();
        $channel->confirm_select();
        $body = json_encode([
            'id' => '019a5a6e-0000-7000-8000-0000000000b1',
            'source' => 'trade-maneuver',
            'type' => 'tm.signal-outcome.v1',
            'time' => '2026-11-09T10:00:00.250000Z',
            ...$fields,
        ], JSON_THROW_ON_ERROR);
        $channel->basic_publish(new AMQPMessage($body, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]), 'tm.signal-outcomes', 'demo-analytics');
        $channel->wait_for_pending_acks(5.0);
        $tradeManeuver->close();
    }
}
