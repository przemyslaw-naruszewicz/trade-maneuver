<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\ConsoleCommands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use TradeManeuver\Trading\Application\InvestmentConfiguration\Command\CreateInvestmentConfiguration\CreateInvestmentConfigurationCommand as CreateInvestmentConfiguration;
use TradeManeuver\Trading\Application\InvestmentConfiguration\Command\CreateInvestmentConfiguration\CreateInvestmentConfigurationCommandHandler;

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
    public function handle(CreateInvestmentConfigurationCommandHandler $handler): int
    {
        $currency = $this->stringOption('currency');

        $handler->handle(new CreateInvestmentConfiguration(
            'console:'.$this->getName(),
            $this->stringArgument('reference'),
            $currency,
            $this->stringOption('brokerage-account'),
            $this->stringOption('order-amount'),
            $this->stringOption('order-amount-currency', $currency),
            $this->stringOption('limit-price-tolerance'),
            $this->listOption('authorized-analytics-application'),
        ));

        $this->info('The investment configuration was created.');

        return self::SUCCESS;
    }

    private function stringArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : '';
    }

    private function stringOption(string $name, string $default = ''): string
    {
        $value = $this->option($name);

        return is_string($value) ? $value : $default;
    }

    /**
     * @return list<string>
     */
    private function listOption(string $name): array
    {
        $values = $this->option($name);
        $strings = [];
        foreach (is_array($values) ? $values : [] as $value) {
            if (is_string($value)) {
                $strings[] = $value;
            }
        }

        return $strings;
    }
}
