<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Order;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Unit\Trading\InvestmentConfiguration\Support\InvestmentConfigurationBuilder;
use Tests\Unit\Trading\Order\Support\OrderBuilder;
use Tests\Unit\Trading\TradeSignal\Support\TradeSignalBuilder;
use Throwable;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository\InvestmentConfigurationRepositoryInterface;
use TradeManeuver\Trading\Domain\Order\Repository\OrderRepositoryInterface;
use TradeManeuver\Trading\Domain\TradeSignal\Repository\TradeSignalRepositoryInterface;
use TradeManeuver\Trading\Infrastructure\Order\ReadModel\OrderAwaitingApprovalReadModel;

final class OrderAwaitingApprovalReadModelTest extends TestCase
{
    use RefreshDatabase;

    private const string OTHER_TRADE_SIGNAL_ID = '019a5a6e-0000-7000-8000-0000000000a2';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(InvestmentConfigurationRepositoryInterface::class)->add(InvestmentConfigurationBuilder::anInvestmentConfiguration()->create());
        $this->app->make(TradeSignalRepositoryInterface::class)->add(TradeSignalBuilder::received(referencePrice: '10.00'));
        $this->app->make(OrderRepositoryInterface::class)->add(OrderBuilder::awaitingApproval());
        DB::table('trading_trade_signals')->insert([
            'id' => self::OTHER_TRADE_SIGNAL_ID,
            'source' => 'demo-analytics',
            'received_at' => '2026-11-07 09:30:00+00',
            'outcome' => 'SignalRejected',
        ]);
    }

    /**
     * A complete row for another trade signal, which the orders table itself would accept.
     *
     * @return array<string, string|int>
     */
    private static function anotherOrder(): array
    {
        return [
            'id' => '019a5a6e-0000-7000-8000-0000000000c2',
            'trade_signal_id' => self::OTHER_TRADE_SIGNAL_ID,
            'investment_configuration_id' => InvestmentConfigurationBuilder::ID,
            'status' => 'awaiting_approval',
            'execution_progress' => 'none',
            'direction' => 'buy',
            'instrument_symbol' => 'DEMO_A',
            'instrument_mic' => 'DEMO',
            'limit_price' => '10.20',
            'quantity' => 98,
            'order_amount' => '1000.00',
            'limit_price_tolerance' => '2.00',
            'currency' => 'EUR',
            'brokerage_account' => 'DEMO-ACCOUNT-1',
            'created_at' => '2026-11-09 10:00:00+00',
        ];
    }

    /**
     * @return iterable<string, array{Closure(): mixed}>
     */
    public static function writes(): iterable
    {
        yield 'saving a changed instance' => [static function (): void {
            $order = OrderAwaitingApprovalReadModel::query()->sole();
            $order->setAttribute('quantity', 1);
            $order->save();
        }];
        yield 'deleting an instance' => [static fn (): ?bool => OrderAwaitingApprovalReadModel::query()->sole()->delete()];
        yield 'creating an instance' => [static fn (): OrderAwaitingApprovalReadModel => OrderAwaitingApprovalReadModel::query()->forceCreate(self::anotherOrder())];
        yield 'updating through the query builder' => [static fn (): int => OrderAwaitingApprovalReadModel::query()->update(['quantity' => 1])];
        yield 'deleting through the query builder' => [static fn (): mixed => OrderAwaitingApprovalReadModel::query()->delete()];
        yield 'inserting through the query builder' => [static fn (): bool => OrderAwaitingApprovalReadModel::query()->insert(self::anotherOrder())];
        yield 'truncating through the query builder' => [static function (): void {
            OrderAwaitingApprovalReadModel::query()->truncate();
        }];
    }

    /**
     * @param  Closure(): mixed  $write
     */
    #[DataProvider('writes')]
    public function test_a_write_through_the_read_model_throws_and_changes_nothing(Closure $write): void
    {
        $ordersBefore = DB::table('trading_orders')->get()->all();

        $thrown = null;
        try {
            // A savepoint keeps the test's transaction usable after PostgreSQL refuses the write.
            DB::transaction($write);
        } catch (Throwable $exception) {
            $thrown = $exception;
        }

        $this->assertInstanceOf(QueryException::class, $thrown, 'The write was expected to throw.');
        $this->assertEquals($ordersBefore, DB::table('trading_orders')->get()->all());
    }
}
