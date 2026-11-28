<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Order;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\Trading\Order\Support\FailingOrderRefusedHandler;
use Tests\TestCase;
use Tests\Unit\Trading\InvestmentConfiguration\Support\InvestmentConfigurationBuilder;
use Tests\Unit\Trading\Order\Support\OrderBuilder;
use Tests\Unit\Trading\TradeSignal\Support\TradeSignalBuilder;
use TradeManeuver\Trading\Application\Order\Command\RefuseOrder\RefuseOrderCommand;
use TradeManeuver\Trading\Application\Order\Command\RefuseOrder\RefuseOrderCommandHandler;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository\InvestmentConfigurationRepositoryInterface;
use TradeManeuver\Trading\Domain\Order\Event\OrderRefused;
use TradeManeuver\Trading\Domain\Order\Repository\OrderRepositoryInterface;
use TradeManeuver\Trading\Domain\TradeSignal\Repository\TradeSignalRepositoryInterface;
use TradeManeuver\Trading\Infrastructure\Messaging\LaravelDomainEventBus;

/**
 * Runs without a wrapping test transaction, so every write the refusal makes is really committed or rolled back.
 */
final class RefuseOrderAtomicityTest extends TestCase
{
    use DatabaseTruncation;

    private const string OPERATOR_ID = '019a5a6e-0000-7000-8000-0000000000d1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-09T11:00:00Z'));
        $this->app->make(InvestmentConfigurationRepositoryInterface::class)->add(InvestmentConfigurationBuilder::anInvestmentConfiguration()->create());
        $this->app->make(TradeSignalRepositoryInterface::class)->add(TradeSignalBuilder::received(referencePrice: '10.00'));
        $this->app->make(OrderRepositoryInterface::class)->add(OrderBuilder::awaitingApproval());
    }

    protected function tearDown(): void
    {
        DB::unprepared('TRUNCATE trading_orders, trading_trade_signal_evaluation_steps, trading_trade_signals, trading_investment_configurations, trading_outbox_messages, trading_audit_records, jobs');

        parent::tearDown();
    }

    public function test_a_failing_domain_event_handler_rolls_back_the_refusal_its_audit_record_and_the_queued_step(): void
    {
        $this->app->make(LaravelDomainEventBus::class)->listen(OrderRefused::class, FailingOrderRefusedHandler::class);

        $failure = null;
        try {
            $this->app->make(RefuseOrderCommandHandler::class)->handle(new RefuseOrderCommand(OrderBuilder::ID, self::OPERATOR_ID, 'Refused before the failure.'));
        } catch (RuntimeException $caught) {
            $failure = $caught;
        }

        $this->assertSame(FailingOrderRefusedHandler::MESSAGE, $failure?->getMessage());
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame('awaiting_approval', DB::table('trading_orders')->where('id', OrderBuilder::ID)->value('status'));
        $this->assertDatabaseEmpty('trading_audit_records');
        $this->assertDatabaseEmpty('trading_outbox_messages');
        $this->assertDatabaseEmpty('jobs');
    }
}
