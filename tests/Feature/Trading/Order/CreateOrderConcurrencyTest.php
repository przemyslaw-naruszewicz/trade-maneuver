<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Order;

use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;
use Tests\Unit\Trading\InvestmentConfiguration\Support\InvestmentConfigurationBuilder;
use Tests\Unit\Trading\Order\Support\OrderBuilder;
use Tests\Unit\Trading\TradeSignal\Support\TradeSignalBuilder;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository\InvestmentConfigurationRepositoryInterface;
use TradeManeuver\Trading\Domain\Order\Repository\OrderRepositoryInterface;
use TradeManeuver\Trading\Domain\TradeSignal\Repository\TradeSignalRepositoryInterface;

/**
 * Two connections create an order for the same trade signal at the same time, bypassing the orchestrator's lock on
 * the trade signal. The test's connection adds its order inside an open transaction; a separate process with its own
 * connection then adds another order for the trade signal and waits for that transaction on the uniqueness constraint.
 */
final class CreateOrderConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    private const string SECOND_ORDER_ID = '019a5a6e-0000-7000-8000-0000000000c2';

    private const int WAIT_ATTEMPTS = 100;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(InvestmentConfigurationRepositoryInterface::class)->add(InvestmentConfigurationBuilder::anInvestmentConfiguration()->create());
        $this->app->make(TradeSignalRepositoryInterface::class)->add(TradeSignalBuilder::received(referencePrice: '10.00'));
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        DB::unprepared('TRUNCATE trading_orders, trading_trade_signal_evaluation_steps, trading_trade_signals, trading_investment_configurations');

        parent::tearDown();
    }

    public function test_concurrent_order_creation_for_one_trade_signal_leaves_exactly_one_order(): void
    {
        DB::beginTransaction();
        $this->app->make(OrderRepositoryInterface::class)->add(OrderBuilder::awaitingApproval());

        $second = $this->startAddingAnOrder(self::SECOND_ORDER_ID);
        $secondWaited = $this->waitUntilWaitingOrFinished($second);
        DB::commit();
        $output = $second->wait()->output();

        $this->assertSame([OrderBuilder::ID], DB::table('trading_orders')->pluck('id')->all(), 'Expected exactly the first order.');
        $this->assertSame('TradeSignalAlreadyHasAnOrderException', $output, $second->latestErrorOutput());
        $this->assertTrue($secondWaited, 'The second connection was expected to wait for the first one on the uniqueness constraint.');
    }

    private function startAddingAnOrder(string $orderId): InvokedProcess
    {
        return Process::path(base_path())
            ->env([
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'pgsql',
                'DB_DATABASE' => (string) DB::connection()->getDatabaseName(),
            ])
            ->timeout(60)
            ->start([PHP_BINARY, 'tests/Feature/Trading/Order/Support/add-order.php', $orderId]);
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
