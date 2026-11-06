<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\ConsoleCommands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trading:create-investment-configuration
    {reference : The reference that trade signals use as investmentConfigurationRef}
    {--currency= : The investment configuration currency}
    {--brokerage-account= : The fictional brokerage account identifier at the simulated broker}
    {--order-amount= : The order amount as a decimal string}
    {--order-amount-currency= : The order amount currency, by default the investment configuration currency}
    {--limit-price-tolerance= : The limit price tolerance as a decimal percentage}
    {--authorized-analytics-application=* : An analytics application identifier from the registry}')]
#[Description('Create the investment configuration')]
final class CreateInvestmentConfigurationCommand extends Command
{
    public function handle(): int
    {
        return self::SUCCESS;
    }
}
