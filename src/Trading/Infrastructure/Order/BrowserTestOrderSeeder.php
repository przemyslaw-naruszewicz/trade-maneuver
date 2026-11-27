<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Order;

use Illuminate\Database\Seeder;
use TradeManeuver\Trading\Application\InvestmentConfiguration\Command\CreateInvestmentConfiguration\CreateInvestmentConfigurationCommand;
use TradeManeuver\Trading\Application\InvestmentConfiguration\Command\CreateInvestmentConfiguration\CreateInvestmentConfigurationCommandHandler;

/**
 * Creates the data of the orders mockup for the browser tests through the application's use cases. bin/e2e runs it
 * on the e2e database after the browser-test operator account.
 */
final class BrowserTestOrderSeeder extends Seeder
{
    private const string ANALYTICS_APPLICATION = 'demo-analytics';

    private const string INVESTMENT_CONFIGURATION = 'demo-eur';

    public function run(CreateInvestmentConfigurationCommandHandler $createInvestmentConfiguration): void
    {
        $createInvestmentConfiguration->handle(new CreateInvestmentConfigurationCommand(
            'seeder:'.self::class,
            self::INVESTMENT_CONFIGURATION,
            'EUR',
            'DEMO-ACC-001',
            '200.00',
            'EUR',
            '0.50',
            [self::ANALYTICS_APPLICATION],
        ));
    }
}
