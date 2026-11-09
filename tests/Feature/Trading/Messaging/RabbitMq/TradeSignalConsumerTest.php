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
}
