<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Messaging\RabbitMq;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\Support\Trading\RabbitMqTestClient;
use Tests\Support\Trading\TradeSignalMessageBuilder;
use Tests\Support\Trading\TradingProcesses;
use Tests\TestCase;

/**
 * The whole round trip through RabbitMQ: the local producer publishes a trade signal, and the consumer and the outbox
 * dispatcher run as their own processes.
 */
final class SignalOutcomeDeliveryTest extends TestCase
{
    use DatabaseTruncation;
    use TradingProcesses;

    private RabbitMqTestClient $producer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = RabbitMqTestClient::asProducer('demo-analytics');
        $this->producer->purge(RabbitMqTestClient::outcomeQueueOf('demo-analytics'));
        RabbitMqTestClient::asTradeManeuver()->purge(RabbitMqTestClient::INTAKE_QUEUE);
    }

    protected function tearDown(): void
    {
        $this->killTradingProcesses();
        $this->producer->purge(RabbitMqTestClient::outcomeQueueOf('demo-analytics'));
        $this->producer->close();
        DB::unprepared('TRUNCATE trading_inbox_entries, trading_trade_signals, trading_outbox_messages, trading_audit_records');

        parent::tearDown();
    }

    public function test_a_trade_signal_with_a_contract_violation_ends_with_signal_rejected_in_the_producers_outcome_queue(): void
    {
        $id = TradeSignalMessageBuilder::idFor('purchase');
        $consumer = $this->startTradingProcess('trading:consume-trade-signals');
        $dispatcher = $this->startTradingProcess('trading:dispatch-outbox');

        $this->producer->publishTradeSignal(TradeSignalMessageBuilder::aTradeSignal($id)->with('confidence', '1.5')->body(), 'demo-analytics');
        $this->waitUntil(
            fn (): bool => $this->producer->readyCount(RabbitMqTestClient::outcomeQueueOf('demo-analytics')) === 1,
            'the signal outcome reaches the outcome queue of demo-analytics',
        );
        $consumer->signal(SIGTERM);
        $dispatcher->signal(SIGTERM);
        $this->waitForExit($consumer);
        $this->assertSame(0, $this->waitForExit($dispatcher), $dispatcher->latestErrorOutput());

        $message = $this->producer->take(RabbitMqTestClient::outcomeQueueOf('demo-analytics'));
        $this->assertNotNull($message);
        $outcome = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('trade-maneuver', $outcome['source']);
        $this->assertSame('tm.signal-outcome.v1', $outcome['type']);
        $this->assertSame($id, $outcome['signalId']);
        $this->assertSame('SignalRejected', $outcome['outcome']);
        $this->assertSame(
            [['field' => 'confidence', 'message' => 'The confidence must be a decimal string from 0 to 1, such as 0.85.']],
            $outcome['violations'],
        );
        $this->assertSame($outcome['id'], $message->get('message_id'));
        $this->assertSame(2, $message->get('delivery_mode'));
    }
}
