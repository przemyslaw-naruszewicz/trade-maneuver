<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\InvestmentConfiguration;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CreateInvestmentConfigurationCommandTest extends TestCase
{
    use RefreshDatabase;

    private const string COMMAND = 'trading:create-investment-configuration';

    protected function setUp(): void
    {
        parent::setUp();

        config(['trading.analytics_applications' => [
            ['id' => 'demo-analytics'],
            ['id' => 'demo-research'],
        ]]);
    }

    public function test_valid_input_creates_the_investment_configuration_with_one_audit_record(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-06T10:15:30.123456Z'));

        $this->createInvestmentConfiguration($this->validInput())->assertSuccessful();

        $configurations = DB::table('trading_investment_configurations')->get();
        $this->assertCount(1, $configurations);
        $configuration = $configurations->sole();
        $this->assertSame('demo-investment', $configuration->reference);
        $this->assertSame('EUR', $configuration->currency);
        $this->assertSame('DEMO-ACCOUNT-1', $configuration->brokerage_account);
        $this->assertSame('200.00', $configuration->order_amount);
        $this->assertSame('1.50', $configuration->limit_price_tolerance);
        $this->assertSame(
            ['demo-analytics', 'demo-research'],
            json_decode($configuration->authorized_analytics_applications, true, flags: JSON_THROW_ON_ERROR),
        );

        $auditRecord = DB::table('trading_audit_records')->sole();
        $this->assertSame('console:'.self::COMMAND, $auditRecord->actor);
        $this->assertTrue(CarbonImmutable::parse('2026-11-06T10:15:30.123456Z')->equalTo(CarbonImmutable::parse($auditRecord->occurred_at)));
        $this->assertSame('investment_configuration', $auditRecord->entity_type);
        $this->assertSame($configuration->id, $auditRecord->entity_id);
        $this->assertSame('created', $auditRecord->action);
        $expectedValues = [
            'reference' => 'demo-investment',
            'currency' => 'EUR',
            'brokerageAccount' => 'DEMO-ACCOUNT-1',
            'orderAmount' => '200.00',
            'limitPriceTolerance' => '1.50',
            'authorizedAnalyticsApplications' => ['demo-analytics', 'demo-research'],
        ];
        $storedValues = json_decode($auditRecord->values, true, flags: JSON_THROW_ON_ERROR);
        // jsonb stores object keys in its own order.
        ksort($expectedValues);
        ksort($storedValues);
        $this->assertSame($expectedValues, $storedValues);
    }

    public function test_a_second_run_is_refused_and_changes_nothing(): void
    {
        $this->createInvestmentConfiguration($this->validInput())->assertSuccessful();
        $before = $this->storedRecords();

        $this->createInvestmentConfiguration($this->validInput(['reference' => 'demo-second-investment']))
            ->expectsOutputToContain('An investment configuration already exists.')
            ->assertFailed();

        $this->assertSame($before, $this->storedRecords());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function orderAmountsNotGreaterThanZero(): iterable
    {
        yield 'zero' => ['0.00'];
        yield 'negative' => ['-200.00'];
    }

    #[DataProvider('orderAmountsNotGreaterThanZero')]
    public function test_an_order_amount_not_greater_than_zero_is_refused_without_side_effects(string $orderAmount): void
    {
        $this->assertRefusedWithoutSideEffects(
            ['--order-amount' => $orderAmount],
            'The order amount must be greater than zero.',
        );
    }

    public function test_an_order_amount_in_another_currency_is_refused_without_side_effects(): void
    {
        $this->assertRefusedWithoutSideEffects(
            ['--order-amount-currency' => 'USD'],
            'The order amount must be in the investment configuration currency EUR, not USD.',
        );
    }

    /**
     * @param  array<string, string|list<string>>  $overrides
     */
    private function assertRefusedWithoutSideEffects(array $overrides, string $message): void
    {
        $this->createInvestmentConfiguration($this->validInput($overrides))
            ->expectsOutputToContain($message)
            ->assertFailed();

        $this->assertDatabaseEmpty('trading_investment_configurations');
        $this->assertDatabaseEmpty('trading_audit_records');
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function storedRecords(): array
    {
        return [
            'investment_configurations' => DB::table('trading_investment_configurations')->get()->map(fn (object $row): array => (array) $row)->values()->all(),
            'audit_records' => DB::table('trading_audit_records')->get()->map(fn (object $row): array => (array) $row)->values()->all(),
        ];
    }

    /**
     * @param  array<string, string|list<string>>  $input
     */
    private function createInvestmentConfiguration(array $input): PendingCommand
    {
        $command = $this->artisan(self::COMMAND, $input);
        $this->assertInstanceOf(PendingCommand::class, $command);

        return $command;
    }

    /**
     * @param  array<string, string|list<string>>  $overrides
     * @return array<string, string|list<string>>
     */
    private function validInput(array $overrides = []): array
    {
        return array_replace([
            'reference' => 'demo-investment',
            '--currency' => 'EUR',
            '--brokerage-account' => 'DEMO-ACCOUNT-1',
            '--order-amount' => '200.00',
            '--order-amount-currency' => 'EUR',
            '--limit-price-tolerance' => '1.50',
            '--authorized-analytics-application' => ['demo-research', 'demo-analytics'],
        ], $overrides);
    }
}
