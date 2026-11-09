<?php

declare(strict_types=1);

namespace Tests\Feature\DemoAnalytics;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Trading\RabbitMqTestClient;
use Tests\TestCase;

/**
 * The publishing command against the RabbitMQ test vhost. No consumer runs, so every published message stays in Trade
 * Maneuver's intake queue, where the test reads it as Trade Maneuver.
 */
final class PublishTradeSignalCommandTest extends TestCase
{
    private const string COMMAND = 'demo-analytics:publish-trade-signal';

    private RabbitMqTestClient $tradeManeuver;

    protected function setUp(): void
    {
        parent::setUp();

        config(['demo_analytics.investment_configuration_ref' => 'demo-investment']);
        $this->tradeManeuver = RabbitMqTestClient::asTradeManeuver();
        $this->tradeManeuver->purge(RabbitMqTestClient::INTAKE_QUEUE);
    }

    protected function tearDown(): void
    {
        $this->tradeManeuver->purge(RabbitMqTestClient::INTAKE_QUEUE);
        $this->tradeManeuver->close();

        parent::tearDown();
    }

    public function test_a_failed_publication_is_reported_as_an_error(): void
    {
        config(['demo_analytics.rabbitmq.password' => 'not-the-demo-analytics-password']);

        $exitCode = Artisan::call(self::COMMAND);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('The trade signal was not published:', Artisan::output());
        $this->assertNothingPublished();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function symbolsWithoutTheDemoPrefix(): iterable
    {
        yield 'prefix not at the start' => ['XDEMO_A'];
        yield 'hyphen instead of underscore' => ['DEMO-A'];
        yield 'lower case' => ['demo_a'];
        yield 'nothing after the prefix' => ['DEMO_'];
        yield 'no prefix' => ['A'];
    }

    #[DataProvider('symbolsWithoutTheDemoPrefix')]
    public function test_a_symbol_without_the_demo_prefix_is_refused_and_nothing_is_published(string $symbol): void
    {
        $exitCode = Artisan::call(self::COMMAND, ['--symbol' => $symbol]);

        $this->assertSame(2, $exitCode);
        $this->assertStringContainsString('The symbol must start with DEMO_', Artisan::output());
        $this->assertNothingPublished();
    }

    private function assertNothingPublished(): void
    {
        $this->assertSame(0, $this->tradeManeuver->readyCount(RabbitMqTestClient::INTAKE_QUEUE), 'Nothing was expected to be published.');
    }
}
