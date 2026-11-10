<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Order;

use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Unit\Trading\InvestmentConfiguration\Support\InvestmentConfigurationBuilder;
use Tests\Unit\Trading\Order\Support\OrderBuilder;
use Tests\Unit\Trading\TradeSignal\Support\TradeSignalBuilder;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository\InvestmentConfigurationRepositoryInterface;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationId;
use TradeManeuver\Trading\Domain\Order\Order;
use TradeManeuver\Trading\Domain\Order\Repository\Exception\OrderDoesNotExistException;
use TradeManeuver\Trading\Domain\Order\Repository\OrderRepositoryInterface;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;
use TradeManeuver\Trading\Domain\TradeSignal\Repository\TradeSignalRepositoryInterface;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcomeId;

final class OrderRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(InvestmentConfigurationRepositoryInterface::class)->add(InvestmentConfigurationBuilder::anInvestmentConfiguration()->create());
    }

    public function test_an_order_round_trips_with_its_exact_amounts_and_its_settings_snapshot(): void
    {
        // The worked example: 12.345 EUR at 1.5 % gives the limit price 12.53 EUR, and 1000.00 EUR buys 79 units.
        $order = $this->orderOf('12.345', '1000.00', '1.50');
        $repository = $this->app->make(OrderRepositoryInterface::class);

        $repository->add($order);
        $reloaded = $repository->get($order->id());

        $this->assertSame(OrderBuilder::ID, $reloaded->id()->toString());
        $this->assertEquals($order->state(), $reloaded->state());
        $this->assertSame([], $reloaded->releaseEvents());
        $row = DB::table('trading_orders')->sole();
        $this->assertSame(TradeSignalBuilder::ID, $row->trade_signal_id);
        $this->assertSame(InvestmentConfigurationBuilder::ID, $row->investment_configuration_id);
        $this->assertSame('awaiting_approval', $row->status);
        $this->assertSame('none', $row->execution_progress);
        $this->assertSame('buy', $row->direction);
        $this->assertSame('DEMO_A', $row->instrument_symbol);
        $this->assertSame('DEMO', $row->instrument_mic);
        $this->assertSame('12.53', $row->limit_price);
        $this->assertSame(79, $row->quantity);
        $this->assertSame('1000.00', $row->order_amount);
        $this->assertSame('1.50', $row->limit_price_tolerance);
        $this->assertSame('EUR', $row->currency);
        $this->assertSame('DEMO-ACCOUNT-1', $row->brokerage_account);
        $this->assertSame('2026-11-09 10:00:00.5+00', $row->created_at);
    }

    public function test_amounts_keep_every_digit_through_persistence(): void
    {
        // Nineteen significant digits: a conversion through float would change the last ones.
        $order = $this->orderOf('9876543210.12', '12345678901234567.89', '0');
        $repository = $this->app->make(OrderRepositoryInterface::class);

        $repository->add($order);
        $state = $repository->get($order->id())->state();

        $this->assertSame('9876543210.12', $state->limitPrice->amount());
        $this->assertSame('12345678901234567.89', $state->settings->orderAmount->amount());
        $this->assertSame(1_249_999, $state->quantity->units());
        $this->assertSame('0.00', $state->settings->limitPriceTolerance->toString());
    }

    public function test_an_order_that_was_not_added_does_not_exist(): void
    {
        $this->expectException(OrderDoesNotExistException::class);
        $this->expectExceptionMessage('The order "'.OrderBuilder::ID.'" does not exist.');

        $this->app->make(OrderRepositoryInterface::class)->get(new OrderId(OrderBuilder::ID));
    }

    public function test_the_next_identities_differ(): void
    {
        $repository = $this->app->make(OrderRepositoryInterface::class);

        $this->assertNotSame($repository->nextIdentity()->toString(), $repository->nextIdentity()->toString());
    }

    private function orderOf(string $referencePrice, string $orderAmount, string $limitPriceTolerance): Order
    {
        $tradeSignal = TradeSignalBuilder::received(referencePrice: $referencePrice);
        $this->app->make(TradeSignalRepositoryInterface::class)->add($tradeSignal);
        $order = $tradeSignal->createOrder(
            new OrderId(OrderBuilder::ID),
            new InvestmentConfigurationId(InvestmentConfigurationBuilder::ID),
            OrderBuilder::settings($orderAmount, $limitPriceTolerance),
            new SignalOutcomeId(TradeSignalBuilder::OUTCOME_ID),
            new DateTimeImmutable(OrderBuilder::CREATED_AT),
        );
        $this->assertNotNull($order);

        return $order;
    }
}
