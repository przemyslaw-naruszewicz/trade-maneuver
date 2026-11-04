<?php

declare(strict_types=1);

namespace Tests\Feature\Trading;

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
}
