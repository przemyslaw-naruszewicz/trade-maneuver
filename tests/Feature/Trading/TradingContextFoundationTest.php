<?php

declare(strict_types=1);

namespace Tests\Feature\Trading;

use Illuminate\Support\Facades\Process;
use ReflectionClass;
use TradeManeuver\Trading\Infrastructure\Providers\TradingServiceProvider;
use Tests\TestCase;

final class TradingContextFoundationTest extends TestCase
{
    public function test_a_trading_context_class_autoloads_from_src_in_tests(): void
    {
        $this->assertTrue(class_exists(TradingServiceProvider::class), 'The TradeManeuver namespace does not autoload.');

        $this->assertSame(
            base_path('src/Trading/Infrastructure/Providers/TradingServiceProvider.php'),
            (new ReflectionClass(TradingServiceProvider::class))->getFileName(),
        );
    }
    public function test_a_trading_context_class_autoloads_in_the_application(): void
    {
        $classCheck = sprintf(
            'echo class_exists(%s) ? "autoloaded" : "missing";',
            var_export(TradingServiceProvider::class, true),
        );

        $result = Process::path(base_path())->run([PHP_BINARY, 'artisan', 'tinker', '--execute', $classCheck]);

        $this->assertStringContainsString('autoloaded', $result->output());
    }
    public function test_the_trading_service_provider_is_registered(): void
    {
        $this->assertTrue($this->app->providerIsLoaded(TradingServiceProvider::class));
    }
    public function test_the_trading_service_provider_merges_the_context_configuration(): void
    {
        $this->assertTrue(config()->has('trading'));
    }
    public function test_the_trading_service_provider_loads_the_context_migration_directory(): void
    {
        $this->assertContains(
            base_path('src/Trading/Infrastructure/Resources/Migrations'),
            $this->app->make('migrator')->paths(),
        );
    }
}
