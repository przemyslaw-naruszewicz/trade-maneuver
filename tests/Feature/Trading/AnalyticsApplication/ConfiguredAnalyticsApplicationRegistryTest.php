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
        config(['trading.analytics_applications' => [
            ['id' => 'demo-analytics', 'rabbitmq_user' => 'demo-analytics-publisher'],
            ['id' => 'demo-research', 'rabbitmq_user' => 'demo-research-publisher'],
        ]]);

        $registry = $this->app->make(AnalyticsApplicationRegistryInterface::class);

        $this->assertTrue($registry->isKnown(new AnalyticsApplicationId('demo-analytics')));
        $this->assertTrue($registry->isKnown(new AnalyticsApplicationId('demo-research')));
        $this->assertFalse($registry->isKnown(new AnalyticsApplicationId('demo-unknown')));
    }

    public function test_each_analytics_application_publishes_only_as_its_registered_rabbitmq_user(): void
    {
        config(['trading.analytics_applications' => [
            ['id' => 'demo-analytics', 'rabbitmq_user' => 'demo-analytics-publisher'],
            ['id' => 'demo-research', 'rabbitmq_user' => 'demo-research-publisher'],
        ]]);

        $registry = $this->app->make(AnalyticsApplicationRegistryInterface::class);

        $this->assertTrue($registry->publishesAs(new AnalyticsApplicationId('demo-analytics'), 'demo-analytics-publisher'));
        $this->assertFalse($registry->publishesAs(new AnalyticsApplicationId('demo-analytics'), 'demo-research-publisher'));
        $this->assertFalse($registry->publishesAs(new AnalyticsApplicationId('demo-unknown'), 'demo-analytics-publisher'));
    }

    public function test_the_configuration_reads_the_registry_from_the_environment(): void
    {
        $_SERVER['TRADING_ANALYTICS_APPLICATIONS'] = ' demo-analytics : demo-analytics-publisher , ,demo-research:demo-research-publisher,demo-unpaired';
        try {
            $configuration = require base_path('src/Trading/Infrastructure/Resources/config.php');
        } finally {
            unset($_SERVER['TRADING_ANALYTICS_APPLICATIONS']);
        }

        $this->assertSame(
            [
                ['id' => 'demo-analytics', 'rabbitmq_user' => 'demo-analytics-publisher'],
                ['id' => 'demo-research', 'rabbitmq_user' => 'demo-research-publisher'],
                ['id' => 'demo-unpaired', 'rabbitmq_user' => ''],
            ],
            $configuration['analytics_applications'],
        );
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function invalidEntries(): iterable
    {
        yield 'missing id' => [[['name' => 'demo-analytics', 'rabbitmq_user' => 'demo-analytics']], 'id'];
        yield 'empty id' => [[['id' => ' ', 'rabbitmq_user' => 'demo-analytics']], 'id'];
        yield 'not an entry' => [['demo-analytics'], 'id'];
        yield 'missing RabbitMQ user' => [[['id' => 'demo-analytics']], 'rabbitmq_user'];
        yield 'empty RabbitMQ user' => [[['id' => 'demo-analytics', 'rabbitmq_user' => '']], 'rabbitmq_user'];
    }

    /**
     * @param  array<mixed>  $entries
     */
    #[DataProvider('invalidEntries')]
    public function test_an_invalid_entry_is_refused(array $entries, string $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Each entry in trading.analytics_applications needs a non-empty string "%s".', $key));

        config(['trading.analytics_applications' => $entries]);

        $this->app->make(AnalyticsApplicationRegistryInterface::class);
    }
}
