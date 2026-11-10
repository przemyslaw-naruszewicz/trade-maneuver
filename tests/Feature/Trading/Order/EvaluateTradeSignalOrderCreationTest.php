<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Order;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
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
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository\Exception\InvestmentConfigurationDoesNotExistException;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

/**
 * The order creation step of an evaluation, against the test database. Only the broker gateway is substituted, and
 * the time is fixed. The investment configuration has the order amount 1000.00 EUR and the tolerance 2 %.
 */
final class EvaluateTradeSignalOrderCreationTest extends TestCase
{
    use DatabaseTruncation;

    private DeterministicBrokerGateway $brokerGateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brokerGateway = new DeterministicBrokerGateway;
        $this->brokerGateway->listAvailable('DEMO_A', 'DEMO', 'EUR');
        $this->app->instance(BrokerGatewayInterface::class, $this->brokerGateway);
        config(['trading.analytics_applications' => [['id' => 'demo-analytics', 'rabbitmq_user' => 'demo-analytics']]]);
        $this->app->make(CreateInvestmentConfigurationCommandHandler::class)->handle(new CreateInvestmentConfigurationCommand(
            'phpunit',
            'demo-investment',
            'EUR',
            'DEMO-ACCOUNT-1',
            '1000.00',
            'EUR',
            '2.00',
            ['demo-analytics'],
        ));
        $this->travelTo('2026-11-09 10:00:00.25Z');
    }

    protected function tearDown(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS trading_test_refuse_order_audit ON trading_audit_records;
            DROP FUNCTION IF EXISTS trading_test_refuse_order_audit();
            TRUNCATE trading_inbox_entries, trading_trade_signals, trading_trade_signal_evaluation_steps, trading_orders, trading_outbox_messages, trading_audit_records, trading_investment_configurations, jobs, failed_jobs;
            SQL);

        parent::tearDown();
    }

    public function test_the_order_commits_with_its_audit_record_and_is_logged(): void
    {
        $id = $this->acceptATradeSignal('10.00');
        $log = Log::spy();

        $this->evaluate($id);

        $order = DB::table('trading_orders')->sole();
        $this->assertSame($id, $order->trade_signal_id);
        $this->assertSame('10.20', $order->limit_price);
        $this->assertSame(98, $order->quantity);
        $this->assertSame('2026-11-09 10:00:00.25+00', $order->created_at);
        $audit = DB::table('trading_audit_records')->where('entity_type', 'order')->sole();
        $this->assertSame('trade-maneuver', $audit->actor);
        $this->assertSame($order->id, $audit->entity_id);
        $this->assertSame('created', $audit->action);
        $this->assertSame('2026-11-09 10:00:00.25+00', $audit->occurred_at);
        // jsonb keeps its own key order, so the values are compared without it.
        $this->assertEquals([
            'tradeSignalId' => $id,
            'investmentConfigurationId' => $order->investment_configuration_id,
            'status' => 'awaiting_approval',
            'direction' => 'buy',
            'instrumentSymbol' => 'DEMO_A',
            'instrumentMic' => 'DEMO',
            'limitPrice' => '10.20',
            'quantity' => '98',
            'orderAmount' => '1000.00',
            'limitPriceTolerance' => '2.00',
            'currency' => 'EUR',
            'brokerageAccount' => 'DEMO-ACCOUNT-1',
        ], $values = json_decode((string) $audit->values, true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame('98', $values['quantity'], 'The audit trail keeps the quantity as a string.');
        $this->assertSame(['instrument_checks', 'order_creation', 'validity_and_direction'], DB::table('trading_trade_signal_evaluation_steps')->orderBy('step')->pluck('step')->all());
        $this->assertNull(DB::table('trading_trade_signals')->sole()->outcome);
        $this->assertDatabaseEmpty('trading_outbox_messages');
        $log->shouldHaveReceived('info')->with('An order awaiting approval is created.', ['signalId' => $id, 'orderId' => $order->id])->once();
    }

    public function test_a_rerun_after_a_crash_before_order_creation_creates_the_order_without_a_second_lookup(): void
    {
        $id = $this->acceptATradeSignal('10.00');
        $this->refuseTheAuditRecordOfAnOrder();

        try {
            $this->evaluate($id);
            $this->fail('The order creation was expected to fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('order audit refused', $exception->getMessage());
        }

        $this->assertDatabaseEmpty('trading_orders');
        $this->assertSame(['instrument_checks', 'validity_and_direction'], DB::table('trading_trade_signal_evaluation_steps')->orderBy('step')->pluck('step')->all(), 'The order, its audit record, and its step were expected to roll back together.');

        DB::unprepared('DROP TRIGGER trading_test_refuse_order_audit ON trading_audit_records');
        $this->evaluate($id);

        $this->assertSame(1, $this->brokerGateway->lookups(), 'The rerun was expected to continue with order creation.');
        $this->assertSame(1, DB::table('trading_orders')->where('trade_signal_id', $id)->count());
        $this->assertSame(1, DB::table('trading_audit_records')->where('entity_type', 'order')->count());
    }

    public function test_a_decline_at_order_creation_commits_the_signal_outcome_with_its_audit_record_and_passes_no_step(): void
    {
        $id = $this->acceptATradeSignal('1000.01');

        $this->evaluate($id);

        $tradeSignal = DB::table('trading_trade_signals')->sole();
        $this->assertSame('SignalDeclined', $tradeSignal->outcome);
        $this->assertSame('OrderAmountTooSmall', $tradeSignal->outcome_reason);
        $audit = DB::table('trading_audit_records')->where('action', 'declined')->sole();
        $this->assertSame($id, $audit->entity_id);
        $this->assertEquals(['outcomeId' => $tradeSignal->outcome_id, 'reason' => 'OrderAmountTooSmall'], json_decode((string) $audit->values, true));
        $this->assertSame($tradeSignal->outcome_id, DB::table('trading_outbox_messages')->sole()->message_id);
        $this->assertDatabaseEmpty('trading_orders');
        $this->assertSame(['instrument_checks', 'validity_and_direction'], DB::table('trading_trade_signal_evaluation_steps')->orderBy('step')->pluck('step')->all());

        $this->evaluate($id);

        $this->assertSame(1, DB::table('trading_outbox_messages')->count(), 'A rerun was expected to record no second signal outcome.');
    }

    public function test_an_evaluation_without_the_investment_configuration_fails_and_creates_no_order(): void
    {
        $id = $this->acceptATradeSignal('10.00');
        DB::table('trading_investment_configurations')->delete();

        try {
            $this->evaluate($id);
            $this->fail('The evaluation was expected to fail.');
        } catch (InvestmentConfigurationDoesNotExistException $exception) {
            $this->assertStringContainsString('demo-investment', $exception->getMessage());
        }

        $this->assertDatabaseEmpty('trading_orders');
        $this->assertNull(DB::table('trading_trade_signals')->sole()->outcome);
    }

    private function acceptATradeSignal(string $referencePrice): string
    {
        $id = TradeSignalMessageBuilder::idFor('purchase');
        $body = TradeSignalMessageBuilder::aTradeSignal($id)->with('referencePrice.amount', $referencePrice)->body();
        $this->app->make(ReceiveTradeSignalCommandHandler::class)->handle(new ReceiveTradeSignalCommand($body, 'demo-analytics'));

        return $id;
    }

    private function evaluate(string $id): void
    {
        $this->app->make(TradeSignalEvaluationOrchestrator::class)->evaluate(new TradeSignalId($id));
    }

    private function refuseTheAuditRecordOfAnOrder(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION trading_test_refuse_order_audit() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'order audit refused';
            END
            $$;
            CREATE TRIGGER trading_test_refuse_order_audit BEFORE INSERT ON trading_audit_records
                FOR EACH ROW WHEN (NEW.entity_type = 'order') EXECUTE FUNCTION trading_test_refuse_order_audit();
            SQL);
    }
}
