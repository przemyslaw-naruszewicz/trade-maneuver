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
 * The trade signal consumer runs as its own process against the RabbitMQ and the database of the tests. A test trigger
 * on trading_trade_signals either waits for an advisory lock, holding the intake transaction open before its commit, or
 * fails, like an unavailable database.
 */
final class TradeSignalConsumerTest extends TestCase
{
    use DatabaseTruncation;
    use TradingProcesses;

    private const int LOCK_KEY = 5005;

    private RabbitMqTestClient $tradeManeuver;

    private RabbitMqTestClient $producer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tradeManeuver = RabbitMqTestClient::asTradeManeuver();
        $this->producer = RabbitMqTestClient::asProducer('demo-analytics');
        $this->tradeManeuver->purge(RabbitMqTestClient::INTAKE_QUEUE);
        $this->tradeManeuver->purge(RabbitMqTestClient::DEAD_LETTER_QUEUE);
    }

    protected function tearDown(): void
    {
        DB::select('SELECT pg_advisory_unlock_all()');
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS trading_test_trade_signal_insert ON trading_trade_signals;
            DROP FUNCTION IF EXISTS trading_test_hold_trade_signal_insert();
            DROP FUNCTION IF EXISTS trading_test_fail_trade_signal_insert();
            TRUNCATE trading_inbox_entries, trading_trade_signals, trading_outbox_messages, trading_audit_records;
            SQL);
        $this->tradeManeuver->purge(RabbitMqTestClient::INTAKE_QUEUE);
        $this->tradeManeuver->purge(RabbitMqTestClient::DEAD_LETTER_QUEUE);
        $this->tradeManeuver->close();
        $this->producer->close();

        parent::tearDown();
    }

    public function test_a_trade_signal_is_not_acknowledged_before_the_intake_transaction_commits(): void
    {
        $this->holdTradeSignalInserts();
        DB::select('SELECT pg_advisory_lock(?)', [self::LOCK_KEY]);
        $consumer = $this->startTradingProcess('trading:consume-trade-signals');
        $this->producer->publishTradeSignal($this->tradeSignalBody(), 'demo-analytics');
        $this->waitUntil(
            static fn (): bool => (int) DB::scalar("SELECT count(*) FROM pg_locks WHERE locktype = 'advisory' AND NOT granted") > 0,
            'the consumer waits inside the intake transaction',
        );

        $consumer->signal(SIGKILL);
        $this->waitForExit($consumer);

        $this->waitUntil(
            fn (): bool => $this->tradeManeuver->readyCount(RabbitMqTestClient::INTAKE_QUEUE) === 1,
            'RabbitMQ returns the unacknowledged trade signal to the intake queue',
        );
        $this->assertDatabaseEmpty('trading_trade_signals');
    }

    public function test_a_failure_before_commit_leads_to_redelivery_and_the_trade_signal_is_processed_once(): void
    {
        $this->failTradeSignalInserts();
        $consumer = $this->startTradingProcess('trading:consume-trade-signals');
        $this->producer->publishTradeSignal($this->tradeSignalBody(), 'demo-analytics');

        $this->assertNotSame(0, $this->waitForExit($consumer), 'The consumer was expected to stop on the database failure.');
        $this->waitUntil(
            fn (): bool => $this->tradeManeuver->readyCount(RabbitMqTestClient::INTAKE_QUEUE) === 1,
            'the trade signal is back in the intake queue',
        );
        $this->assertDatabaseEmpty('trading_trade_signals');

        DB::unprepared('DROP TRIGGER trading_test_trade_signal_insert ON trading_trade_signals');
        $restarted = $this->startTradingProcess('trading:consume-trade-signals');
        $this->waitUntil(static fn (): bool => DB::table('trading_trade_signals')->exists(), 'the redelivered trade signal is stored');
        $restarted->signal(SIGTERM);
        $this->waitForExit($restarted);

        $this->assertSame(1, DB::table('trading_trade_signals')->count());
        $this->assertSame(1, DB::table('trading_inbox_entries')->count());
        $this->assertSame(0, $this->tradeManeuver->readyCount(RabbitMqTestClient::INTAKE_QUEUE), 'The stored trade signal was expected to be acknowledged.');
    }

    public function test_a_message_that_cannot_be_processed_ends_in_the_dead_letter_queue_and_is_not_redelivered(): void
    {
        $consumer = $this->startTradingProcess('trading:consume-trade-signals');
        $this->producer->publishTradeSignal('not a trade signal', 'demo-analytics');

        $this->waitUntil(
            fn (): bool => $this->tradeManeuver->readyCount(RabbitMqTestClient::DEAD_LETTER_QUEUE) === 1,
            'the message reaches the dead letter queue',
        );
        $consumer->signal(SIGTERM);
        $this->waitForExit($consumer);

        $this->assertSame(0, $this->tradeManeuver->readyCount(RabbitMqTestClient::INTAKE_QUEUE), 'The message was expected not to return to the intake queue.');
        $this->assertSame('not a trade signal', $this->tradeManeuver->take(RabbitMqTestClient::DEAD_LETTER_QUEUE)?->getBody());
        $this->assertSame(1, DB::table('trading_audit_records')->where('action', 'set_aside')->count());
    }

    public function test_the_consumer_stops_gracefully_on_sigterm_without_losing_an_unacknowledged_trade_signal(): void
    {
        $this->holdTradeSignalInserts();
        DB::select('SELECT pg_advisory_lock(?)', [self::LOCK_KEY]);
        $consumer = $this->startTradingProcess('trading:consume-trade-signals');
        $this->producer->publishTradeSignal($this->tradeSignalBody(), 'demo-analytics');
        $this->producer->publishTradeSignal(
            TradeSignalMessageBuilder::aTradeSignal(TradeSignalMessageBuilder::idFor('second purchase'))->body(),
            'demo-analytics',
        );
        $this->waitUntil(
            static fn (): bool => (int) DB::scalar("SELECT count(*) FROM pg_locks WHERE locktype = 'advisory' AND NOT granted") > 0,
            'the consumer waits inside the intake transaction of the first trade signal',
        );

        $consumer->signal(SIGTERM);
        DB::select('SELECT pg_advisory_unlock(?)', [self::LOCK_KEY]);

        $this->assertSame(0, $this->waitForExit($consumer), $consumer->latestErrorOutput());
        $this->assertSame([TradeSignalMessageBuilder::idFor('purchase')], DB::table('trading_trade_signals')->pluck('id')->all());
        $this->assertSame(1, $this->tradeManeuver->readyCount(RabbitMqTestClient::INTAKE_QUEUE), 'The second trade signal was expected back in the intake queue.');
    }

    private function tradeSignalBody(): string
    {
        return TradeSignalMessageBuilder::aTradeSignal(TradeSignalMessageBuilder::idFor('purchase'))->body();
    }

    private function holdTradeSignalInserts(): void
    {
        DB::unprepared(sprintf(<<<'SQL'
            CREATE OR REPLACE FUNCTION trading_test_hold_trade_signal_insert() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                PERFORM pg_advisory_xact_lock(%d);
                RETURN NEW;
            END
            $$;
            CREATE TRIGGER trading_test_trade_signal_insert BEFORE INSERT ON trading_trade_signals
                FOR EACH ROW EXECUTE FUNCTION trading_test_hold_trade_signal_insert();
            SQL, self::LOCK_KEY));
    }

    private function failTradeSignalInserts(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION trading_test_fail_trade_signal_insert() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'the database is unavailable for the test';
            END
            $$;
            CREATE TRIGGER trading_test_trade_signal_insert BEFORE INSERT ON trading_trade_signals
                FOR EACH ROW EXECUTE FUNCTION trading_test_fail_trade_signal_insert();
            SQL);
    }
}
