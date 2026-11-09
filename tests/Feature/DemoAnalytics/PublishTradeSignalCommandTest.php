<?php

declare(strict_types=1);

namespace Tests\Feature\DemoAnalytics;

use Carbon\CarbonImmutable;
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

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function invalidOptionValues(): iterable
    {
        yield 'unknown direction' => [['--direction' => 'sideways'], 'The direction must be long or short.'];
        $confidence = 'The confidence must be a decimal from 0 to 1, such as 0.75.';
        yield 'confidence above 1' => [['--confidence' => '1.01'], $confidence];
        yield 'negative confidence' => [['--confidence' => '-0.1'], $confidence];
        yield 'confidence that is not a number' => [['--confidence' => 'high'], $confidence];
        yield 'confidence without a leading digit' => [['--confidence' => '.5'], $confidence];
        $horizon = 'The horizon sessions must be a positive whole number, such as 5.';
        yield 'zero horizon sessions' => [['--horizon-sessions' => '0'], $horizon];
        yield 'negative horizon sessions' => [['--horizon-sessions' => '-1'], $horizon];
        yield 'fractional horizon sessions' => [['--horizon-sessions' => '1.5'], $horizon];
        yield 'horizon sessions in words' => [['--horizon-sessions' => 'five'], $horizon];
        yield 'expected move minimum that is not a number' => [['--expected-move-min' => 'low'], 'The expected move minimum must be a decimal percentage, such as 1.0.'];
        yield 'expected move maximum that is not a number' => [['--expected-move-max' => 'high'], 'The expected move maximum must be a decimal percentage, such as 3.0.'];
        yield 'expected move minimum above its maximum' => [
            ['--expected-move-min' => '3.5', '--expected-move-max' => '3.0'],
            'The expected move minimum must not be greater than its maximum.',
        ];
        $referencePrice = 'The reference price must be a decimal greater than zero, such as 12.50.';
        yield 'zero reference price' => [['--reference-price' => '0.00'], $referencePrice];
        yield 'negative reference price' => [['--reference-price' => '-12.50'], $referencePrice];
        yield 'reference price that is not a number' => [['--reference-price' => 'cheap'], $referencePrice];
        $currency = 'The currency must be an ISO 4217 code, such as EUR.';
        yield 'currency in lower case' => [['--currency' => 'eur'], $currency];
        yield 'currency that is not an ISO 4217 code' => [['--currency' => 'DEM0'], $currency];
        $validUntil = 'The signal validity must be a date and time in UTC, such as 2026-11-12T16:00:00Z.';
        yield 'signal validity in words' => [['--valid-until' => 'tomorrow'], $validUntil];
        yield 'signal validity on an impossible date' => [['--valid-until' => '2026-11-31T16:00:00Z'], $validUntil];
        yield 'signal validity outside UTC' => [['--valid-until' => '2026-11-12T16:00:00+01:00'], $validUntil];
        $validityEnd = 'The signal validity must end after the trade signal is issued.';
        yield 'signal validity ending when the trade signal is issued' => [['--valid-until' => '2026-11-09T10:00:00Z'], $validityEnd];
        yield 'signal validity ending before the trade signal is issued' => [['--valid-until' => '2026-11-08T10:00:00Z'], $validityEnd];
        yield 'blank investment configuration reference' => [
            ['--investment-configuration-ref' => ' '],
            'The investment configuration reference must not be empty; set --investment-configuration-ref or DEMO_ANALYTICS_INVESTMENT_CONFIGURATION_REF.',
        ];
    }

    /**
     * @param  array<string, string>  $options
     */
    #[DataProvider('invalidOptionValues')]
    public function test_an_invalid_option_value_is_refused_and_nothing_is_published(array $options, string $expectedError): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-09T10:00:00Z'));

        $exitCode = Artisan::call(self::COMMAND, $options);

        $this->assertSame(2, $exitCode);
        $this->assertStringContainsString($expectedError, Artisan::output());
        $this->assertNothingPublished();
    }

    public function test_a_missing_investment_configuration_reference_is_refused_and_nothing_is_published(): void
    {
        config(['demo_analytics.investment_configuration_ref' => '']);

        $exitCode = Artisan::call(self::COMMAND);

        $this->assertSame(2, $exitCode);
        $this->assertStringContainsString('The investment configuration reference must not be empty', Artisan::output());
        $this->assertNothingPublished();
    }

    private function assertNothingPublished(): void
    {
        $this->assertSame(0, $this->tradeManeuver->readyCount(RabbitMqTestClient::INTAKE_QUEUE), 'Nothing was expected to be published.');
    }
}
