<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\TradeSignal;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\Support\Trading\TradeSignalMessageBuilder;
use Tests\TestCase;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommand;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommandHandler;

/**
 * Runs without a wrapping test transaction, so every write the handler makes is really committed or rolled back.
 */
final class ReceiveTradeSignalAtomicityTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        config(['trading.analytics_applications' => [['id' => 'demo-analytics', 'rabbitmq_user' => 'demo-analytics']]]);
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION trading_test_fail_outbox_write() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'outbox write failed for the test with % audit records written', (SELECT count(*) FROM trading_audit_records);
            END
            $$;
            CREATE TRIGGER trading_test_fail_outbox_write BEFORE INSERT ON trading_outbox_messages
                FOR EACH ROW EXECUTE FUNCTION trading_test_fail_outbox_write();
            SQL);
    }

    protected function tearDown(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS trading_test_fail_outbox_write ON trading_outbox_messages;
            DROP FUNCTION IF EXISTS trading_test_fail_outbox_write();
            TRUNCATE trading_inbox_entries, trading_trade_signals, trading_outbox_messages, trading_audit_records;
            SQL);

        parent::tearDown();
    }

    public function test_a_failing_outbox_write_rolls_back_the_inbox_entry_trade_signal_and_audit_record(): void
    {
        $body = TradeSignalMessageBuilder::aTradeSignal(TradeSignalMessageBuilder::idFor('purchase'))
            ->with('confidence', '1.5')
            ->body();

        try {
            $this->app->make(ReceiveTradeSignalCommandHandler::class)->handle(new ReceiveTradeSignalCommand($body, 'demo-analytics'));
            $this->fail('The outbox write was expected to fail.');
        } catch (QueryException $exception) {
            // The failure must come after the audit record is written, or the rollback of the audit record is not shown.
            $this->assertStringContainsString('outbox write failed for the test with 1 audit records written', $exception->getMessage());
        }

        $this->assertSame(0, DB::transactionLevel());
        $this->assertDatabaseEmpty('trading_inbox_entries');
        $this->assertDatabaseEmpty('trading_trade_signals');
        $this->assertDatabaseEmpty('trading_audit_records');
        $this->assertDatabaseEmpty('trading_outbox_messages');
    }
}
