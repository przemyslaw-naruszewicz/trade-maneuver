<?php

declare(strict_types=1);

namespace Tests\Feature\DemoAnalytics;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\Trading\RabbitMqTestClient;
use Tests\Support\Trading\TradingProcesses;
use Tests\TestCase;

/**
 * The demo analytics application's commands against Trade Maneuver's real consumer and queue worker, which run as their
 * own processes on the test database and the RabbitMQ test vhost.
 */
final class DemoAnalyticsFlowTest extends TestCase
{
    use DatabaseTruncation;
    use TradingProcesses;

    private const string OUTCOME_QUEUE = 'tm.signal-outcomes.demo-analytics';

    private RabbitMqTestClient $tradeManeuver;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'demo_analytics.investment_configuration_ref' => 'demo-investment',
            'trading.analytics_applications' => [['id' => 'demo-analytics', 'rabbitmq_user' => 'demo-analytics']],
        ]);
        $this->tradeManeuver = RabbitMqTestClient::asTradeManeuver();
        $this->tradeManeuver->purge(RabbitMqTestClient::INTAKE_QUEUE);
        $this->purgeOutcomeQueue();
    }

    protected function tearDown(): void
    {
        $this->killTradingProcesses();
        DB::unprepared('TRUNCATE trading_investment_configurations, trading_inbox_entries, trading_trade_signals, trading_outbox_messages, trading_audit_records, jobs, failed_jobs');
        $this->tradeManeuver->purge(RabbitMqTestClient::INTAKE_QUEUE);
        $this->tradeManeuver->close();
        $this->purgeOutcomeQueue();

        parent::tearDown();
    }

    public function test_a_trade_signal_published_with_the_defaults_is_accepted_at_intake_as_coming_from_the_demo_analytics_application(): void
    {
        $this->createInvestmentConfigurationAuthorizingTheDemo();
        $consumer = $this->startTradingProcess('trading:consume-trade-signals');

        $id = $this->publishTradeSignal();

        retry(
            self::WAIT_ATTEMPTS,
            fn () => $this->assertTrue(DB::table('trading_trade_signals')->where('id', $id)->exists(), 'the trade signal is stored'),
            self::WAIT_PAUSE_MILLISECONDS,
        );
        $consumer->signal(SIGTERM);
        $consumer->wait();

        $tradeSignal = DB::table('trading_trade_signals')->sole();
        $this->assertSame('demo-analytics', $tradeSignal->source);
        $this->assertNull($tradeSignal->outcome, 'A trade signal accepted at intake was expected to have no signal outcome yet.');
        $this->assertSame('demo-investment', $tradeSignal->investment_configuration_ref);
        $this->assertSame('DEMO_A', $tradeSignal->instrument_symbol);
        $this->assertSame('DEMO', $tradeSignal->instrument_mic);
        $this->assertSame('long', $tradeSignal->direction);
        $this->assertSame('EUR', $tradeSignal->reference_price_currency);
    }

    public function test_publishing_the_same_message_twice_creates_one_trade_signal(): void
    {
        $this->createInvestmentConfigurationAuthorizingTheDemo();
        $consumer = $this->startTradingProcess('trading:consume-trade-signals');

        $id = $this->publishTradeSignal(['--twice' => true]);

        retry(
            self::WAIT_ATTEMPTS,
            fn () => $this->assertTrue(
                DB::table('trading_audit_records')->where('entity_id', $id)->where('action', 'duplicate_delivered')->exists(),
                'the second delivery is handled as a duplicate',
            ),
            self::WAIT_PAUSE_MILLISECONDS,
        );
        $consumer->signal(SIGTERM);
        $consumer->wait();

        $this->assertSame(1, DB::table('trading_trade_signals')->where('id', $id)->count());
        $this->assertSame(1, DB::table('trading_trade_signals')->count());
        $this->assertSame(
            ['duplicate_delivered', 'received'],
            DB::table('trading_audit_records')->where('entity_id', $id)->orderBy('action')->pluck('action')->all(),
        );
    }

    private function createInvestmentConfigurationAuthorizingTheDemo(): void
    {
        $this->artisan('trading:create-investment-configuration', [
            'reference' => 'demo-investment',
            '--currency' => 'EUR',
            '--brokerage-account' => 'DEMO-ACCOUNT-1',
            '--order-amount' => '200.00',
            '--limit-price-tolerance' => '1.50',
            '--authorized-analytics-application' => ['demo-analytics'],
        ])->assertSuccessful();
    }

    /**
     * Runs the publishing command and returns the trade signal `id` it printed.
     *
     * @param  array<string, string|bool>  $options
     */
    private function publishTradeSignal(array $options = []): string
    {
        $exitCode = Artisan::call('demo-analytics:publish-trade-signal', $options);
        $output = Artisan::output();
        $this->assertSame(0, $exitCode, $output);
        $this->assertSame(1, preg_match('/Published trade signal ([0-9a-f-]{36})\./', $output, $matches), $output);

        return $matches[1];
    }

    private function purgeOutcomeQueue(): void
    {
        $producer = RabbitMqTestClient::asProducer('demo-analytics');
        $producer->purge(self::OUTCOME_QUEUE);
        $producer->close();
    }
}
