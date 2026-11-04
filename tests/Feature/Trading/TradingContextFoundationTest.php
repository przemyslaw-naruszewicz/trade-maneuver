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
}
