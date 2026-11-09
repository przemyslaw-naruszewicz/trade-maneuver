<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use TradeManeuver\DemoAnalytics\Ui\ConsoleCommands\PublishTradeSignalCommand;
use TradeManeuver\DemoAnalytics\Ui\ConsoleCommands\ReadSignalOutcomesCommand;

final class DemoAnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Resources/config.php', 'demo_analytics');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([PublishTradeSignalCommand::class, ReadSignalOutcomesCommand::class]);
        }
    }
}
