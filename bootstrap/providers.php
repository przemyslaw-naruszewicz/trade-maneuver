<?php

use App\Providers\AppServiceProvider;
use TradeManeuver\DemoAnalytics\Infrastructure\Providers\DemoAnalyticsServiceProvider;
use TradeManeuver\Trading\Infrastructure\Providers\TradingServiceProvider;

return [
    AppServiceProvider::class,
    TradingServiceProvider::class,
    DemoAnalyticsServiceProvider::class,
];
