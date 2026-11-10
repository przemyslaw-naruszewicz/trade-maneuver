<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\TradeSignal;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Support\Trading\TradeSignalMessageBuilder;
use Tests\Support\Trading\TradingProcesses;
use Tests\TestCase;
use TradeManeuver\Trading\Application\InvestmentConfiguration\Command\CreateInvestmentConfiguration\CreateInvestmentConfigurationCommand;
use TradeManeuver\Trading\Application\InvestmentConfiguration\Command\CreateInvestmentConfiguration\CreateInvestmentConfigurationCommandHandler;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommand;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommandHandler;
use TradeManeuver\Trading\Application\Workflow\TradeSignalEvaluation\TradeSignalEvaluationOrchestrator;
use TradeManeuver\Trading\Infrastructure\Messaging\Jobs\EvaluateTradeSignalJob;

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
        DB::unprepared('TRUNCATE trading_inbox_entries, trading_trade_signals, trading_trade_signal_evaluation_steps, trading_outbox_messages, trading_audit_records, trading_investment_configurations, jobs, failed_jobs');

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

    public function test_running_the_evaluation_job_twice_produces_one_signal_outcome(): void
    {
        $id = $this->acceptAShortTradeSignal();
        // The queue delivers the evaluation job a second time, as at-least-once delivery allows.
        DB::statement('INSERT INTO jobs (queue, payload, attempts, reserved_at, available_at, created_at) SELECT queue, payload, attempts, reserved_at, available_at, created_at FROM jobs');

        $this->runTheQueueWorkerOnce();
        $this->runTheQueueWorkerOnce();

        $this->assertOneSignalDeclinedOutcome($id);
        $this->assertSame(1, DB::table('trading_audit_records')->where('entity_id', $id)->where('action', 'declined')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count(), 'The second evaluation was expected to finish without a failure.');
        $this->assertStringContainsString('PublishSignalOutcomeJob', DB::table('jobs')->sole()->payload, 'Only the publication of the one signal outcome was expected to stay queued.');
    }

    public function test_a_decline_commits_the_signal_outcome_with_its_audit_record_and_is_logged(): void
    {
        $this->travelTo('2026-11-09 10:00:00.25Z');
        $id = $this->acceptAShortTradeSignal();
        $this->travel(1)->seconds();
        $log = Log::spy();

        $this->runTheQueueWorkerOnce();

        $tradeSignal = DB::table('trading_trade_signals')->sole();
        $this->assertSame('SignalDeclined', $tradeSignal->outcome);
        $this->assertSame('UnsupportedDirection', $tradeSignal->outcome_reason);
        $this->assertSame('2026-11-09 10:00:01.25+00', $tradeSignal->outcome_occurred_at);
        $outboxMessage = DB::table('trading_outbox_messages')->sole();
        $this->assertSame($tradeSignal->outcome_id, $outboxMessage->message_id);
        $this->assertSame('demo-analytics', $outboxMessage->recipient);
        $this->assertEquals([
            'id' => $tradeSignal->outcome_id,
            'source' => 'trade-maneuver',
            'type' => 'tm.signal-outcome.v1',
            'time' => '2026-11-09T10:00:01.250000Z',
            'signalId' => $id,
            'outcome' => 'SignalDeclined',
            'reason' => 'UnsupportedDirection',
        ], json_decode((string) $outboxMessage->payload, true));
        $audit = DB::table('trading_audit_records')->where('action', 'declined')->sole();
        $this->assertSame('trade-maneuver', $audit->actor);
        $this->assertSame('trade_signal', $audit->entity_type);
        $this->assertSame($id, $audit->entity_id);
        $this->assertSame('2026-11-09 10:00:01.25+00', $audit->occurred_at);
        $this->assertEquals(['outcomeId' => $tradeSignal->outcome_id, 'reason' => 'UnsupportedDirection'], json_decode((string) $audit->values, true));
        $log->shouldHaveReceived('info')->once()->with('A trade signal is declined.', [
            'signalId' => $id,
            'outcomeId' => $tradeSignal->outcome_id,
            'reason' => 'UnsupportedDirection',
        ]);
    }

    public function test_the_evaluation_of_a_trade_signal_that_has_a_signal_outcome_is_logged_and_changes_nothing(): void
    {
        $id = TradeSignalMessageBuilder::idFor('purchase');
        $body = TradeSignalMessageBuilder::aTradeSignal($id)->with('confidence', '1.5')->body();
        $this->app->make(ReceiveTradeSignalCommandHandler::class)->handle(new ReceiveTradeSignalCommand($body, 'demo-analytics'));
        $rejected = DB::table('trading_trade_signals')->sole();
        $log = Log::spy();

        (new EvaluateTradeSignalJob($id))->handle($this->app->make(TradeSignalEvaluationOrchestrator::class));

        $this->assertEquals($rejected, DB::table('trading_trade_signals')->sole());
        $this->assertSame(1, DB::table('trading_outbox_messages')->count());
        $this->assertSame(0, DB::table('trading_audit_records')->where('action', 'declined')->count());
        $log->shouldHaveReceived('info')->once()->with('A trade signal already has a signal outcome, so it is not evaluated again.', ['signalId' => $id]);
    }

    public function test_a_failed_outbox_write_rolls_back_the_decline_and_its_audit_record(): void
    {
        $id = $this->acceptAShortTradeSignal();
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION trading_test_fail_outbox_write() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'outbox write failed for the test with % declines recorded', (SELECT count(*) FROM trading_audit_records WHERE action = 'declined');
            END
            $$;
            CREATE TRIGGER trading_test_fail_outbox_write BEFORE INSERT ON trading_outbox_messages FOR EACH ROW EXECUTE FUNCTION trading_test_fail_outbox_write();
            SQL);

        try {
            (new EvaluateTradeSignalJob($id))->handle($this->app->make(TradeSignalEvaluationOrchestrator::class));
            $this->fail('The outbox write was expected to fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('outbox write failed for the test with 1 declines recorded', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER trading_test_fail_outbox_write ON trading_outbox_messages; DROP FUNCTION trading_test_fail_outbox_write();');
        }

        $this->assertSame(0, DB::transactionLevel());
        $this->assertNull(DB::table('trading_trade_signals')->sole()->outcome);
        $this->assertSame(0, DB::table('trading_audit_records')->where('action', 'declined')->count());
        $this->assertDatabaseEmpty('trading_outbox_messages');
    }

    public function test_the_evaluation_job_is_queued_in_the_intake_transaction(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION trading_test_fail_job_write() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'job write failed for the test with % trade signals written', (SELECT count(*) FROM trading_trade_signals);
            END
            $$;
            CREATE TRIGGER trading_test_fail_job_write BEFORE INSERT ON jobs FOR EACH ROW EXECUTE FUNCTION trading_test_fail_job_write();
            SQL);

        try {
            $this->acceptAShortTradeSignal();
            $this->fail('The job write was expected to fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('job write failed for the test with 1 trade signals written', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER trading_test_fail_job_write ON jobs; DROP FUNCTION trading_test_fail_job_write();');
        }

        $this->assertSame(0, DB::transactionLevel());
        $this->assertDatabaseEmpty('trading_trade_signals');
        $this->assertDatabaseEmpty('trading_inbox_entries');
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
        $payload = json_decode((string) $entries->firstOrFail()->payload, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('SignalDeclined', $payload['outcome']);
        $this->assertSame('UnsupportedDirection', $payload['reason']);
    }
}
