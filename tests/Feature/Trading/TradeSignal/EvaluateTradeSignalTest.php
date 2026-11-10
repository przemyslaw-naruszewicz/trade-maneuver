<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\TradeSignal;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\Trading\TradeSignalMessageBuilder;
use Tests\Support\Trading\TradingProcesses;
use Tests\TestCase;
use TradeManeuver\Trading\Application\InvestmentConfiguration\Command\CreateInvestmentConfiguration\CreateInvestmentConfigurationCommand;
use TradeManeuver\Trading\Application\InvestmentConfiguration\Command\CreateInvestmentConfiguration\CreateInvestmentConfigurationCommandHandler;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommand;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommandHandler;

/**
 * The evaluation of an accepted trade signal through Laravel's PostgreSQL job queue, against the test database. The
 * trade signal is short, so its evaluation ends with `SignalDeclined`. Each in-process queue worker run processes at
 * most one job.
 */
final class EvaluateTradeSignalTest extends TestCase
{
    use DatabaseTruncation;
    use TradingProcesses;

    protected function setUp(): void
    {
        parent::setUp();

        config(['trading.analytics_applications' => [['id' => 'demo-analytics', 'rabbitmq_user' => 'demo-analytics']]]);
        $this->app->make(CreateInvestmentConfigurationCommandHandler::class)->handle(new CreateInvestmentConfigurationCommand(
            'phpunit',
            'demo-investment',
            'EUR',
            'DEMO-ACCOUNT-1',
            '200.00',
            'EUR',
            '1.50',
            ['demo-analytics'],
        ));
    }

    protected function tearDown(): void
    {
        $this->killTradingProcesses();
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        DB::unprepared('TRUNCATE trading_inbox_entries, trading_trade_signals, trading_outbox_messages, trading_audit_records, trading_investment_configurations, jobs, failed_jobs');

        parent::tearDown();
    }

    public function test_a_trade_signal_accepted_before_a_worker_crash_is_evaluated_after_the_restart(): void
    {
        $id = $this->acceptAShortTradeSignal();

        // The test holds the trade signal's row lock, so the worker stops inside the evaluation until it is killed.
        DB::beginTransaction();
        DB::table('trading_trade_signals')->where('id', $id)->lockForUpdate()->sole();
        $worker = $this->startTradingProcess('queue:work', ['database', '--sleep=1']);
        retry(
            self::WAIT_ATTEMPTS,
            fn () => $this->assertSame(1, $this->backendsWaitingForARowLock(), 'the worker waits for the trade signal inside its evaluation'),
            self::WAIT_PAUSE_MILLISECONDS,
        );
        $worker->signal(SIGKILL);
        $worker->wait();
        DB::select("SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = current_database() AND wait_event_type = 'Lock'");
        DB::rollBack();

        $this->assertSame(0, DB::table('trading_outbox_messages')->count(), 'The crashed worker was expected to commit no signal outcome.');
        $this->assertNotNull(DB::table('jobs')->sole()->reserved_at, 'The crashed worker was expected to leave its job reserved.');

        $this->travel((int) config('queue.connections.database.retry_after') + 1)->seconds();
        $this->runTheQueueWorkerOnce();

        $this->assertOneSignalDeclinedOutcome($id);
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    private function acceptAShortTradeSignal(): string
    {
        $id = TradeSignalMessageBuilder::idFor('sale');
        $body = TradeSignalMessageBuilder::aTradeSignal($id)
            ->with('direction', 'short')
            ->with('validUntil', now()->addDays(3)->utc()->format('Y-m-d\TH:i:s\Z'))
            ->body();
        $this->app->make(ReceiveTradeSignalCommandHandler::class)->handle(new ReceiveTradeSignalCommand($body, 'demo-analytics'));

        return $id;
    }

    private function runTheQueueWorkerOnce(): void
    {
        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0]);
    }

    private function backendsWaitingForARowLock(): int
    {
        return (int) DB::selectOne("SELECT count(*) AS waiting FROM pg_locks WHERE locktype = 'transactionid' AND NOT granted")->waiting;
    }

    private function assertOneSignalDeclinedOutcome(string $id): void
    {
        $entries = DB::table('trading_outbox_messages')->whereRaw("payload->>'signalId' = ?", [$id])->get();
        $this->assertCount(1, $entries, 'Expected exactly one signal outcome in the outbox.');
        $payload = json_decode((string) $entries[0]->payload, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('SignalDeclined', $payload['outcome']);
        $this->assertSame('UnsupportedDirection', $payload['reason']);
    }
}
