<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Order;

use Closure;
use DateTimeImmutable;
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
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationId;
use TradeManeuver\Trading\Domain\Order\Order;
use TradeManeuver\Trading\Domain\Order\Repository\OrderRepositoryInterface;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;
use TradeManeuver\Trading\Domain\TradeSignal\Repository\TradeSignalRepositoryInterface;
use TradeManeuver\Trading\Domain\TradeSignal\TradeSignal;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcomeId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;
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

    public function test_it_shows_each_order_awaiting_approval_with_its_trade_signal(): void
    {
        $order = OrderAwaitingApprovalReadModel::soonestValidUntilFirst()->sole();

        $this->assertSame(OrderBuilder::ID, $order->id);
        $this->assertSame('awaiting_approval', $order->status);
        $this->assertSame('10.20', $order->limit_price);
        $this->assertSame('EUR', $order->currency);
        $this->assertSame(98, $order->quantity);
        $this->assertSame('1000.00', $order->order_amount);
        $this->assertSame('none', $order->execution_progress);
        $this->assertSame(TradeSignalBuilder::ID, $order->trade_signal_id);
        $this->assertSame('DEMO_A', $order->instrument_symbol);
        $this->assertSame('DEMO', $order->instrument_mic);
        $this->assertSame('long', $order->direction);
        $this->assertSame('0.85', $order->confidence);
        $this->assertSame(5, $order->horizon_sessions);
        $this->assertSame('2026-11-20', $order->horizon_at);
        $this->assertSame('1.5', $order->expected_move_min);
        $this->assertSame('4.0', $order->expected_move_max);
        $this->assertSame('10.00', $order->reference_price_amount);
        $this->assertSame('EUR', $order->reference_price_currency);
        $this->assertSame('2026-11-07 08:59:00+00', $order->reference_price_as_of);
        $this->assertSame('2026-11-10 16:00:00+00', $order->valid_until);
        $this->assertSame('demo-analytics', $order->analytics_application);
    }

    public function test_the_orders_whose_signal_validity_ends_soonest_come_first(): void
    {
        $this->addOrderOfATradeSignalValidUntil('019a5a6e-0000-7000-8000-0000000000a3', '019a5a6e-0000-7000-8000-0000000000c3', '2026-11-10T12:00:00Z');
        $this->addOrderOfATradeSignalValidUntil('019a5a6e-0000-7000-8000-0000000000a4', '019a5a6e-0000-7000-8000-0000000000c4', '2026-11-11T09:00:00Z');

        $this->assertSame(
            ['019a5a6e-0000-7000-8000-0000000000c3', OrderBuilder::ID, '019a5a6e-0000-7000-8000-0000000000c4'],
            OrderAwaitingApprovalReadModel::soonestValidUntilFirst()->pluck('id')->all(),
        );
    }

    private function addOrderOfATradeSignalValidUntil(string $tradeSignalId, string $orderId, string $validUntil): void
    {
        $tradeSignal = TradeSignal::receive(
            new TradeSignalId($tradeSignalId),
            new AnalyticsApplicationId('demo-analytics'),
            TradeSignalBuilder::content(validUntil: $validUntil),
            new DateTimeImmutable(TradeSignalBuilder::RECEIVED_AT),
        );
        $this->app->make(TradeSignalRepositoryInterface::class)->add($tradeSignal);
        $order = $tradeSignal->createOrder(
            new OrderId($orderId),
            new InvestmentConfigurationId(InvestmentConfigurationBuilder::ID),
            OrderBuilder::settings(),
            new SignalOutcomeId(TradeSignalBuilder::OUTCOME_ID),
            new DateTimeImmutable(OrderBuilder::CREATED_AT),
        );
        $this->assertInstanceOf(Order::class, $order);
        $this->app->make(OrderRepositoryInterface::class)->add($order);
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
        yield 'inserting only the columns of the read model' => [static fn (): bool => OrderAwaitingApprovalReadModel::query()->insert([
            'id' => '019a5a6e-0000-7000-8000-0000000000c2',
            'status' => 'awaiting_approval',
            'limit_price' => '10.20',
            'currency' => 'EUR',
            'quantity' => 98,
            'order_amount' => '1000.00',
            'execution_progress' => 'none',
            'trade_signal_id' => self::OTHER_TRADE_SIGNAL_ID,
        ])];
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
