<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Order;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use TradeManeuver\Trading\Infrastructure\Order\BrowserTestOrderSeeder;

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
    }
}
