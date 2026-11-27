<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Order;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use TradeManeuver\Trading\Infrastructure\Order\BrowserTestOrderSeeder;
use TradeManeuver\Trading\Infrastructure\Order\ReadModel\OrderAwaitingApprovalReadModel;

final class BrowserTestOrderSeederTest extends TestCase
{
    use RefreshDatabase;

    private const string SEEDED_AT = '2026-11-09T10:00:00Z';

    protected function setUp(): void
    {
        parent::setUp();

        config(['trading.analytics_applications' => [['id' => 'demo-analytics', 'rabbitmq_user' => 'demo-analytics']]]);
        $this->travelTo(CarbonImmutable::parse(self::SEEDED_AT));
    }

    public function test_the_seeder_creates_the_investment_configuration_of_the_browser_tests(): void
    {
        $this->seed(BrowserTestOrderSeeder::class);

        $investmentConfiguration = DB::table('trading_investment_configurations')->where('reference', 'demo-eur')->first();
        $this->assertNotNull($investmentConfiguration, 'The investment configuration demo-eur was expected.');
        $this->assertDatabaseCount('trading_investment_configurations', 1);
        $this->assertSame('demo-eur', $investmentConfiguration->reference);
        $this->assertSame('EUR', $investmentConfiguration->currency);
        $this->assertSame('DEMO-ACC-001', $investmentConfiguration->brokerage_account);
        $this->assertSame('200.00', $investmentConfiguration->order_amount);
        $this->assertSame('0.50', $investmentConfiguration->limit_price_tolerance);
        $this->assertSame(['demo-analytics'], json_decode((string) $investmentConfiguration->authorized_analytics_applications, true, 512, JSON_THROW_ON_ERROR));
        $this->assertDatabaseHas('trading_audit_records', ['entity_id' => $investmentConfiguration->id, 'actor' => 'seeder:'.BrowserTestOrderSeeder::class]);
    }

    public function test_the_seeder_creates_the_three_orders_awaiting_approval_of_the_orders_mockup_from_long_trade_signals(): void
    {
        $this->seed(BrowserTestOrderSeeder::class);

        $orders = OrderAwaitingApprovalReadModel::soonestValidUntilFirst()->get();
        $this->assertSame(['DEMO_A', 'DEMO_B', 'DEMO_C'], $orders->pluck('instrument_symbol')->all());
        $this->assertSame(['10.40', '6.20', '24.80'], $orders->pluck('reference_price_amount')->all());
        $this->assertSame(['10.45', '6.23', '24.92'], $orders->pluck('limit_price')->all());
        $this->assertSame([19, 32, 8], $orders->pluck('quantity')->all());
        foreach ($orders as $order) {
            $this->assertSame('DEMO', $order->instrument_mic);
            $this->assertSame('long', $order->direction);
            $this->assertSame('demo-analytics', $order->analytics_application);
            $this->assertSame('EUR', $order->reference_price_currency);
            $this->assertSame('200.00', $order->order_amount);
            $this->assertSame('2026-11-09 10:00:00+00', $order->reference_price_as_of);
            $this->assertGreaterThan(CarbonImmutable::parse(self::SEEDED_AT)->addHours(6), CarbonImmutable::parse($order->valid_until));
        }

        $demoA = $orders->firstOrFail('instrument_symbol', 'DEMO_A');
        $this->assertSame('0.72', $demoA->confidence);
        $this->assertSame(5, $demoA->horizon_sessions);
        $this->assertSame('2.00', $demoA->expected_move_min);
        $this->assertSame('4.00', $demoA->expected_move_max);
        $demoC = $orders->firstOrFail('instrument_symbol', 'DEMO_C');
        $this->assertSame('2026-11-30', $demoC->horizon_at);
        $this->assertNull($demoC->expected_move_min);
        $this->assertNull($demoC->expected_move_max);
    }
}
