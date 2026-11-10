<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\TradeSignal;

use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Tests\Support\Trading\TradeSignalMessageBuilder;
use Tests\TestCase;

/**
 * Two worker processes, each with its own PostgreSQL connection, receive the same trade signal. An advisory lock that
 * a test trigger waits for holds the first worker inside its transaction after its inbox insert, until the second
 * worker is blocked on the same inbox key. Releasing the lock lets both finish in that order.
 */
final class ReceiveTradeSignalConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    private const int LOCK_KEY = 4004;

    private const int WAIT_ATTEMPTS = 100;

    protected function setUp(): void
    {
        parent::setUp();

        DB::unprepared(sprintf(<<<'SQL'
            CREATE OR REPLACE FUNCTION trading_test_hold_trade_signal_insert() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                PERFORM pg_advisory_xact_lock(%d);
                RETURN NEW;
            END
            $$;
            CREATE TRIGGER trading_test_hold_trade_signal_insert BEFORE INSERT ON trading_trade_signals
                FOR EACH ROW EXECUTE FUNCTION trading_test_hold_trade_signal_insert();
            SQL, self::LOCK_KEY));
    }

    protected function tearDown(): void
    {
        DB::select('SELECT pg_advisory_unlock_all()');
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS trading_test_hold_trade_signal_insert ON trading_trade_signals;
            DROP FUNCTION IF EXISTS trading_test_hold_trade_signal_insert();
            TRUNCATE trading_inbox_entries, trading_trade_signals, trading_trade_signal_evaluation_steps, trading_outbox_messages, trading_audit_records, jobs, failed_jobs;
            SQL);

        parent::tearDown();
    }

    public function test_concurrent_deliveries_of_one_trade_signal_create_one_inbox_entry_one_trade_signal_and_one_outcome(): void
    {
        $id = TradeSignalMessageBuilder::idFor('purchase');
        $body = TradeSignalMessageBuilder::aTradeSignal($id)->with('confidence', '1.5')->body();
        DB::select('SELECT pg_advisory_lock(?)', [self::LOCK_KEY]);

        $first = $this->startWorker($body);
        $this->waitUntil("SELECT count(*) AS waiting FROM pg_locks WHERE locktype = 'advisory' AND NOT granted");
        $second = $this->startWorker($body);
        $this->waitUntil("SELECT count(*) AS waiting FROM pg_locks WHERE locktype = 'transactionid' AND NOT granted");
        DB::select('SELECT pg_advisory_unlock(?)', [self::LOCK_KEY]);

        $this->assertSame('Accepted', $first->wait()->output(), $first->latestErrorOutput());
        $this->assertSame('Accepted', $second->wait()->output(), $second->latestErrorOutput());
        $this->assertSame(1, DB::table('trading_inbox_entries')->where('message_id', $id)->count());
        $this->assertSame(1, DB::table('trading_trade_signals')->where('id', $id)->count());
        $this->assertSame(1, DB::table('trading_outbox_messages')->distinct()->count('message_id'), 'Expected at most one signal outcome.');
        $this->assertSame(1, DB::table('trading_audit_records')->where('action', 'duplicate_delivered')->count());
    }

    private function startWorker(string $body): InvokedProcess
    {
        return Process::path(base_path())
            ->env([
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'pgsql',
                'DB_DATABASE' => (string) DB::connection()->getDatabaseName(),
            ])
            ->input($body)
            ->timeout(60)
            ->start([PHP_BINARY, 'tests/Feature/Trading/TradeSignal/Support/receive-trade-signal.php', 'demo-analytics']);
    }

    /**
     * Waits until the query reports a waiting lock, so the test fails when the interleaving it claims did not happen.
     */
    private function waitUntil(string $query): void
    {
        for ($attempt = 0; $attempt < self::WAIT_ATTEMPTS; $attempt++) {
            if ((int) DB::scalar($query) > 0) {
                return;
            }
            usleep(50_000);
        }
        $this->fail('The expected lock wait did not happen: '.$query);
    }
}
