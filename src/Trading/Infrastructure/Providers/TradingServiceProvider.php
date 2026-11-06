<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Providers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\ServiceProvider;
use TradeManeuver\Trading\Application\Port\AuditTrailInterface;
use TradeManeuver\Trading\Application\Port\ClockInterface;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository\InvestmentConfigurationRepositoryInterface;
use TradeManeuver\Trading\Infrastructure\Audit\PostgresAuditTrail;
use TradeManeuver\Trading\Infrastructure\Clock\SystemClock;
use TradeManeuver\Trading\Infrastructure\InvestmentConfiguration\InvestmentConfigurationRepository;
use TradeManeuver\Trading\Ui\ConsoleCommands\CreateInvestmentConfigurationCommand;

final class TradingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Resources/config.php', 'trading');

        $this->app->bind(InvestmentConfigurationRepositoryInterface::class, InvestmentConfigurationRepository::class);
        $this->app->bind(ClockInterface::class, SystemClock::class);
        $this->app->bind(
            AuditTrailInterface::class,
            fn ($app): PostgresAuditTrail => new PostgresAuditTrail($app->make(ConnectionInterface::class)),
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__).'/Resources/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([CreateInvestmentConfigurationCommand::class]);
        }
    }
}
