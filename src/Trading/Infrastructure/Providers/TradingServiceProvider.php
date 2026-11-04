<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

final class TradingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Resources/config.php', 'trading');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__).'/Resources/Migrations');
    }
}
