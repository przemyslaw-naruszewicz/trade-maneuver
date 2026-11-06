<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\AnalyticsApplication;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use TradeManeuver\Trading\Application\Port\AnalyticsApplicationRegistryInterface;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;

final class ConfiguredAnalyticsApplicationRegistryTest extends TestCase
{
    public function test_the_registry_knows_only_the_configured_analytics_applications(): void
    {
        config(['trading.analytics_applications' => [['id' => 'demo-analytics'], ['id' => 'demo-research']]]);

        $registry = $this->app->make(AnalyticsApplicationRegistryInterface::class);

        $this->assertTrue($registry->isKnown(new AnalyticsApplicationId('demo-analytics')));
        $this->assertTrue($registry->isKnown(new AnalyticsApplicationId('demo-research')));
        $this->assertFalse($registry->isKnown(new AnalyticsApplicationId('demo-unknown')));
    }

    public function test_the_configuration_reads_the_registry_from_the_environment(): void
    {
        $_SERVER['TRADING_ANALYTICS_APPLICATIONS'] = ' demo-analytics , ,demo-research';
        try {
            $configuration = require base_path('src/Trading/Infrastructure/Resources/config.php');
        } finally {
            unset($_SERVER['TRADING_ANALYTICS_APPLICATIONS']);
        }

        $this->assertSame(
            [['id' => 'demo-analytics'], ['id' => 'demo-research']],
            $configuration['analytics_applications'],
        );
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function invalidEntries(): iterable
    {
        yield 'missing id' => [[['name' => 'demo-analytics']]];
        yield 'empty id' => [[['id' => ' ']]];
        yield 'not an entry' => [['demo-analytics']];
    }

    /**
     * @param  array<mixed>  $entries
     */
    #[DataProvider('invalidEntries')]
    public function test_an_invalid_entry_is_refused(array $entries): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Each entry in trading.analytics_applications needs a non-empty string "id".');

        config(['trading.analytics_applications' => $entries]);

        $this->app->make(AnalyticsApplicationRegistryInterface::class);
    }
}
