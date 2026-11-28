<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Order;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;
use Tests\Unit\Trading\InvestmentConfiguration\Support\InvestmentConfigurationBuilder;
use Tests\Unit\Trading\Order\Support\OrderBuilder;
use Tests\Unit\Trading\TradeSignal\Support\TradeSignalBuilder;
use TradeManeuver\Trading\Application\Order\Command\ApproveOrder\ApproveOrderCommand;
use TradeManeuver\Trading\Application\Order\Command\ApproveOrder\ApproveOrderCommandHandler;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository\InvestmentConfigurationRepositoryInterface;
use TradeManeuver\Trading\Domain\Order\Repository\OrderRepositoryInterface;
use TradeManeuver\Trading\Domain\TradeSignal\Repository\TradeSignalRepositoryInterface;

/**
 * Two connections decide on the same order at the same time, as a double submit or two tabs do. The test's connection
 * approves it inside an open transaction; a separate process with its own connection loads the order, which still
 * awaits approval there, and refuses it. Its update waits for that transaction and then finds a newer version.
 */
final class OrderDecisionConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    private const string OPERATOR_ID = '019a5a6e-0000-7000-8000-0000000000d1';

    private const int WAIT_ATTEMPTS = 100;

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
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        DB::unprepared('TRUNCATE trading_orders, trading_trade_signal_evaluation_steps, trading_trade_signals, trading_investment_configurations, trading_audit_records, jobs');

        parent::tearDown();
    }

    public function test_a_decision_that_saves_a_stale_version_fails_without_changes(): void
    {
        DB::beginTransaction();
        $this->app->make(ApproveOrderCommandHandler::class)->handle(new ApproveOrderCommand(OrderBuilder::ID, self::OPERATOR_ID, '10.50'));

        $second = $this->startRefusingTheOrder();
        $secondWaited = $this->waitUntilWaitingOrFinished($second);
        DB::commit();
        $output = $second->wait()->output();

        $this->assertSame('OrderAlreadyDecidedException', $output, $second->latestErrorOutput());
        $this->assertTrue($secondWaited, 'The second connection was expected to wait for the first decision on the order row.');
        $order = DB::table('trading_orders')->where('id', OrderBuilder::ID)->sole();
        $this->assertSame('approved', $order->status);
        $this->assertSame('10.50', $order->limit_price);
        $this->assertSame(95, $order->quantity);
        $this->assertNull($order->ended_at);
        $this->assertSame(2, $order->version);
        $this->assertSame(['approved'], DB::table('trading_audit_records')->where('entity_id', OrderBuilder::ID)->where('action', '!=', 'created')->pluck('action')->all());
        $this->assertSame(0, DB::table('jobs')->count(), 'The refusal was expected to queue no recording of a signal outcome.');
    }

    private function startRefusingTheOrder(): InvokedProcess
    {
        return Process::path(base_path())
            ->env([
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'pgsql',
                'DB_DATABASE' => (string) DB::connection()->getDatabaseName(),
            ])
            ->timeout(60)
            ->start([PHP_BINARY, 'tests/Feature/Trading/Order/Support/decide-order.php', OrderBuilder::ID, self::OPERATOR_ID]);
    }

    /**
     * @return bool whether the process waits for a lock that another transaction holds
     */
    private function waitUntilWaitingOrFinished(InvokedProcess $process): bool
    {
        for ($attempt = 0; $attempt < self::WAIT_ATTEMPTS; $attempt++) {
            if ((int) DB::scalar("SELECT count(*) FROM pg_locks WHERE locktype = 'transactionid' AND NOT granted") > 0) {
                return true;
            }
            if (! $process->running()) {
                return false;
            }
            usleep(50_000);
        }

        return false;
    }
}
