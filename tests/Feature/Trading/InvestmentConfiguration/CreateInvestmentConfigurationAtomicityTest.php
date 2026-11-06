<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\InvestmentConfiguration;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use TradeManeuver\Trading\Application\InvestmentConfiguration\Command\CreateInvestmentConfiguration\CreateInvestmentConfigurationCommand;
use TradeManeuver\Trading\Application\InvestmentConfiguration\Command\CreateInvestmentConfiguration\CreateInvestmentConfigurationCommandHandler;

/**
 * Runs without a wrapping test transaction, so every write the handler makes is really committed or rolled back.
 */
final class CreateInvestmentConfigurationAtomicityTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        config(['trading.analytics_applications' => [['id' => 'demo-analytics']]]);
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION trading_test_fail_audit_write() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'audit write failed for the test';
            END
            $$;
            CREATE TRIGGER trading_test_fail_audit_write BEFORE INSERT ON trading_audit_records
                FOR EACH ROW EXECUTE FUNCTION trading_test_fail_audit_write();
            SQL);
    }

    protected function tearDown(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS trading_test_fail_audit_write ON trading_audit_records;
            DROP FUNCTION IF EXISTS trading_test_fail_audit_write();
            TRUNCATE trading_investment_configurations, trading_audit_records;
            SQL);

        parent::tearDown();
    }

    public function test_a_failing_audit_write_rolls_back_the_investment_configuration(): void
    {
        $handler = $this->app->make(CreateInvestmentConfigurationCommandHandler::class);

        try {
            $handler->handle(new CreateInvestmentConfigurationCommand(
                'console:trading:create-investment-configuration',
                'demo-investment',
                'EUR',
                'DEMO-ACCOUNT-1',
                '200.00',
                'EUR',
                '1.50',
                ['demo-analytics'],
            ));
            $this->fail('The audit write was expected to fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('audit write failed for the test', $exception->getMessage());
        }

        $this->assertSame(0, DB::transactionLevel());
        $this->assertDatabaseEmpty('trading_investment_configurations');
        $this->assertDatabaseEmpty('trading_audit_records');
    }
}
