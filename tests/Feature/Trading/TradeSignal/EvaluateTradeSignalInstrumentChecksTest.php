<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\TradeSignal;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Support\Trading\DeterministicBrokerGateway;
use Tests\Support\Trading\TradeSignalMessageBuilder;
use Tests\TestCase;
use TradeManeuver\Trading\Application\InvestmentConfiguration\Command\CreateInvestmentConfiguration\CreateInvestmentConfigurationCommand;
use TradeManeuver\Trading\Application\InvestmentConfiguration\Command\CreateInvestmentConfiguration\CreateInvestmentConfigurationCommandHandler;
use TradeManeuver\Trading\Application\Port\BrokerGatewayInterface;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommand;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommandHandler;
use TradeManeuver\Trading\Application\Workflow\TradeSignalEvaluation\TradeSignalEvaluationOrchestrator;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

/**
 * The instrument checks of an evaluation through Laravel's PostgreSQL job queue, against the test database. Only the
 * broker gateway is substituted, and the time is fixed. Each in-process queue worker run processes at most one job.
 */
final class EvaluateTradeSignalInstrumentChecksTest extends TestCase
{
    use DatabaseTruncation;

    private DeterministicBrokerGateway $brokerGateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brokerGateway = new DeterministicBrokerGateway;
        $this->app->instance(BrokerGatewayInterface::class, $this->brokerGateway);
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
        DB::unprepared('TRUNCATE trading_inbox_entries, trading_trade_signals, trading_trade_signal_evaluation_steps, trading_outbox_messages, trading_audit_records, trading_investment_configurations, jobs, failed_jobs');

        parent::tearDown();
    }

    public function test_a_lookup_timeout_writes_no_signal_outcome_and_the_evaluation_succeeds_on_a_later_retry(): void
    {
        $this->travelTo('2026-11-09 10:00:00Z');
        $this->brokerGateway->listAvailable('DEMO_A', 'DEMO', 'EUR');
        $this->brokerGateway->timeOutNextLookups(1);
        $id = $this->acceptATradeSignal('purchase', 'DEMO_A', '2026-11-12T10:00:00Z');

        $this->runTheQueueWorkerOnce();

        $this->assertDatabaseEmpty('trading_outbox_messages');
        $this->assertNull(DB::table('trading_trade_signals')->sole()->outcome);
        $this->assertSame(0, DB::table('failed_jobs')->count(), 'A lookup timeout was expected to be a recoverable failure.');
        $job = DB::table('jobs')->sole();
        $this->assertSame(1, $job->attempts);
        $this->assertSame(now()->addSeconds(30)->getTimestamp(), $job->available_at, 'The retry was expected to wait 30 seconds.');

        $this->travel(30)->seconds();
        $this->runTheQueueWorkerOnce();

        $this->assertDatabaseEmpty('jobs');
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertNull(DB::table('trading_trade_signals')->sole()->outcome, 'DEMO_A passes the instrument checks.');
        $this->assertDatabaseEmpty('trading_outbox_messages');
        $this->assertSame(
            ['instrument_checks', 'validity_and_direction'],
            DB::table('trading_trade_signal_evaluation_steps')->where('trade_signal_id', $id)->orderBy('step')->pluck('step')->all(),
        );
    }

    public function test_a_rerun_after_signal_validity_ends_continues_with_the_instrument_checks_and_does_not_decline_with_signal_expired(): void
    {
        $this->travelTo('2026-11-09 10:00:00Z');
        $this->brokerGateway->listUnavailable('DEMO_H', 'DEMO', 'EUR');
        $this->brokerGateway->timeOutNextLookups(1);
        $id = $this->acceptATradeSignal('purchase', 'DEMO_H', '2026-11-09T10:00:10Z');
        $this->runTheQueueWorkerOnce();
        $this->assertSame(['validity_and_direction'], DB::table('trading_trade_signal_evaluation_steps')->pluck('step')->all());

        $this->travelTo('2026-11-09 10:01:00Z');
        $this->runTheQueueWorkerOnce();

        $outcomes = DB::table('trading_outbox_messages')->whereRaw("payload->>'signalId' = ?", [$id])->get();
        $this->assertCount(1, $outcomes, 'Expected exactly one signal outcome in the outbox.');
        $payload = json_decode((string) $outcomes->firstOrFail()->payload, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('SignalDeclined', $payload['outcome']);
        $this->assertSame('InstrumentUnavailable', $payload['reason']);
        $this->assertSame('2026-11-09T10:01:00.000000Z', $payload['time']);
        $this->assertSame(['validity_and_direction'], DB::table('trading_trade_signal_evaluation_steps')->pluck('step')->all(), 'A declined trade signal passes no further step.');
    }

    public function test_a_trade_signal_declined_at_the_first_step_is_not_looked_up_at_the_broker(): void
    {
        $this->travelTo('2026-11-09 10:00:00Z');
        $this->brokerGateway->listAvailable('DEMO_A', 'DEMO', 'EUR');
        $this->acceptATradeSignal('sale', 'DEMO_A', '2026-11-12T10:00:00Z', ['direction' => 'short']);

        $this->runTheQueueWorkerOnce();

        $this->assertSame(0, $this->brokerGateway->lookups());
        $this->assertDatabaseEmpty('trading_trade_signal_evaluation_steps');
        $this->assertSame('UnsupportedDirection', DB::table('trading_trade_signals')->sole()->outcome_reason);
    }

    public function test_passing_the_instrument_checks_is_logged_and_a_rerun_after_every_step_looks_nothing_up(): void
    {
        $this->travelTo('2026-11-09 10:00:00Z');
        $this->brokerGateway->listAvailable('DEMO_A', 'DEMO', 'EUR');
        $id = $this->acceptATradeSignal('purchase', 'DEMO_A', '2026-11-12T10:00:00Z');
        $log = Log::spy();

        $this->runTheQueueWorkerOnce();
        $this->evaluate($id);

        $this->assertSame(1, $this->brokerGateway->lookups());
        $this->assertSame(2, DB::table('trading_trade_signal_evaluation_steps')->count());
        $this->assertNull(DB::table('trading_trade_signals')->sole()->outcome);
        $this->assertDatabaseEmpty('trading_outbox_messages');
        $log->shouldHaveReceived('info')->once()->with('A trade signal passed the instrument checks and awaits order creation.', ['signalId' => $id]);
    }

    public function test_a_concurrent_evaluation_that_declines_first_leaves_one_signal_outcome(): void
    {
        $this->travelTo('2026-11-09 10:00:00Z');
        $this->brokerGateway->listUnavailable('DEMO_H', 'DEMO', 'EUR');
        $id = $this->acceptATradeSignal('purchase', 'DEMO_H', '2026-11-12T10:00:00Z');
        $this->brokerGateway->duringNextLookup(fn () => $this->evaluate($id));

        $this->evaluate($id);

        $this->assertSame(2, $this->brokerGateway->lookups());
        $this->assertSame(1, DB::table('trading_outbox_messages')->count());
        $this->assertSame(1, DB::table('trading_audit_records')->where('action', 'declined')->count());
        $this->assertSame('InstrumentUnavailable', DB::table('trading_trade_signals')->sole()->outcome_reason);
    }

    public function test_a_concurrent_evaluation_that_passes_the_instrument_checks_first_is_not_repeated(): void
    {
        $this->travelTo('2026-11-09 10:00:00Z');
        $this->brokerGateway->listAvailable('DEMO_A', 'DEMO', 'EUR');
        $id = $this->acceptATradeSignal('purchase', 'DEMO_A', '2026-11-12T10:00:00Z');
        $this->brokerGateway->duringNextLookup(fn () => $this->evaluate($id));
        $log = Log::spy();

        $this->evaluate($id);

        $this->assertSame(2, DB::table('trading_trade_signal_evaluation_steps')->count());
        $log->shouldHaveReceived('info')->once()->with('A trade signal passed the instrument checks and awaits order creation.', ['signalId' => $id]);
    }

    public function test_a_rejection_at_evaluation_commits_the_signal_outcome_with_its_audit_record_and_is_logged(): void
    {
        $this->travelTo('2026-11-09 10:00:00.25Z');
        $this->brokerGateway->listAvailable('DEMO_A', 'DEMO', 'EUR');
        $id = $this->acceptATradeSignal('purchase', 'DEMO_A', '2026-11-12T10:00:00Z', ['referencePrice.currency' => 'USD']);
        $log = Log::spy();

        $this->runTheQueueWorkerOnce();

        $tradeSignal = DB::table('trading_trade_signals')->sole();
        $this->assertSame('SignalRejected', $tradeSignal->outcome);
        $violation = ['field' => 'referencePrice.currency', 'message' => "The reference price currency must be the instrument's quote currency EUR."];
        $this->assertEquals([
            'id' => $tradeSignal->outcome_id,
            'source' => 'trade-maneuver',
            'type' => 'tm.signal-outcome.v1',
            'time' => '2026-11-09T10:00:00.250000Z',
            'signalId' => $id,
            'outcome' => 'SignalRejected',
            'violations' => [$violation],
        ], json_decode((string) DB::table('trading_outbox_messages')->sole()->payload, true));
        $audit = DB::table('trading_audit_records')->where('action', 'rejected')->sole();
        $this->assertSame('trade-maneuver', $audit->actor);
        $this->assertSame('trade_signal', $audit->entity_type);
        $this->assertSame($id, $audit->entity_id);
        $this->assertSame('2026-11-09 10:00:00.25+00', $audit->occurred_at);
        $description = "referencePrice.currency: The reference price currency must be the instrument's quote currency EUR.";
        $this->assertEquals(['outcomeId' => $tradeSignal->outcome_id, 'violations' => [$description]], json_decode((string) $audit->values, true));
        $log->shouldHaveReceived('info')->once()->with('A trade signal is rejected.', [
            'signalId' => $id,
            'outcomeId' => $tradeSignal->outcome_id,
            'violations' => [$description],
        ]);
        $this->assertSame(['validity_and_direction'], DB::table('trading_trade_signal_evaluation_steps')->pluck('step')->all());
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function acceptATradeSignal(string $alias, string $symbol, string $validUntil, array $fields = []): string
    {
        $id = TradeSignalMessageBuilder::idFor($alias);
        $message = TradeSignalMessageBuilder::aTradeSignal($id)
            ->with('instrument.symbol', $symbol)
            ->with('validUntil', $validUntil);
        foreach ($fields as $path => $value) {
            $message->with($path, $value);
        }
        $body = $message->body();
        $this->app->make(ReceiveTradeSignalCommandHandler::class)->handle(new ReceiveTradeSignalCommand($body, 'demo-analytics'));

        return $id;
    }

    private function evaluate(string $id): void
    {
        $this->app->make(TradeSignalEvaluationOrchestrator::class)->evaluate(new TradeSignalId($id));
    }

    private function runTheQueueWorkerOnce(): void
    {
        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0]);
    }
}
