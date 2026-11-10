<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\TradeSignal;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\Trading\DeterministicBrokerGateway;
use Tests\Support\Trading\TradeSignalMessageBuilder;
use Tests\TestCase;
use TradeManeuver\Trading\Application\InvestmentConfiguration\Command\CreateInvestmentConfiguration\CreateInvestmentConfigurationCommand;
use TradeManeuver\Trading\Application\InvestmentConfiguration\Command\CreateInvestmentConfiguration\CreateInvestmentConfigurationCommandHandler;
use TradeManeuver\Trading\Application\Port\BrokerGatewayInterface;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommand;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommandHandler;

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

    private function acceptATradeSignal(string $alias, string $symbol, string $validUntil): string
    {
        $id = TradeSignalMessageBuilder::idFor($alias);
        $body = TradeSignalMessageBuilder::aTradeSignal($id)
            ->with('instrument.symbol', $symbol)
            ->with('validUntil', $validUntil)
            ->body();
        $this->app->make(ReceiveTradeSignalCommandHandler::class)->handle(new ReceiveTradeSignalCommand($body, 'demo-analytics'));

        return $id;
    }

    private function runTheQueueWorkerOnce(): void
    {
        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0]);
    }
}
