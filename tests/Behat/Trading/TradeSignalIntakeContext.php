<?php

declare(strict_types=1);

namespace Tests\Behat\Trading;

use Behat\Behat\Context\Context;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\Assert;
use Tests\Support\Trading\TradeSignalMessageBuilder;
use TradeManeuver\Trading\Application\InvestmentConfiguration\Command\CreateInvestmentConfiguration\CreateInvestmentConfigurationCommand;
use TradeManeuver\Trading\Application\InvestmentConfiguration\Command\CreateInvestmentConfiguration\CreateInvestmentConfigurationCommandHandler;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommand;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommandHandler;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\TradeSignalIntakeResult;

/**
 * Drives the Trading application boundary through its application commands, against the PostgreSQL test database.
 */
final class TradeSignalIntakeContext implements Context
{
    private static bool $migrated = false;

    private Application $application;

    /** @var list<array{id: string, rabbitmq_user: string}> */
    private array $registry = [];

    /** @var array<string, string> the last delivered body of each trade signal alias */
    private array $bodies = [];

    private ?TradeSignalIntakeResult $lastResult = null;

    #[BeforeScenario]
    public function bootApplication(): void
    {
        $this->application = require dirname(__DIR__, 3).'/bootstrap/app.php';
        $this->application->make(Kernel::class)->bootstrap();

        $database = (string) DB::connection()->getDatabaseName();
        Assert::assertStringEndsWith('_testing', $database, 'Trading scenarios truncate tables, so they need the test database.');
        if (! self::$migrated) {
            Artisan::call('migrate', ['--force' => true]);
            self::$migrated = true;
        }
        DB::statement('TRUNCATE trading_inbox_entries, trading_trade_signals, trading_outbox_messages, trading_audit_records, jobs, failed_jobs, trading_investment_configurations');
    }

    #[AfterScenario]
    public function shutdownApplication(): void
    {
        CarbonImmutable::setTestNow();
        $this->application->flush();
        Facade::clearResolvedInstances();
        Application::setInstance(null);
    }

    #[Given('the analytics application :analyticsApplication publishes as the RabbitMQ user :rabbitMqUser')]
    public function theAnalyticsApplicationPublishesAs(string $analyticsApplication, string $rabbitMqUser): void
    {
        $this->registry[] = ['id' => $analyticsApplication, 'rabbitmq_user' => $rabbitMqUser];
        config(['trading.analytics_applications' => $this->registry]);
    }

    #[Given('the investment configuration :reference authorizes the analytics application :analyticsApplication')]
    public function theInvestmentConfigurationAuthorizes(string $reference, string $analyticsApplication): void
    {
        $this->application->make(CreateInvestmentConfigurationCommandHandler::class)->handle(new CreateInvestmentConfigurationCommand(
            'behat',
            $reference,
            'EUR',
            'DEMO-ACCOUNT-1',
            '200.00',
            'EUR',
            '1.50',
            [$analyticsApplication],
        ));
    }

    /**
     * Fixes the time that the PSR-20 clock binding returns.
     */
    #[Given('the time is :time')]
    public function theTimeIs(string $time): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($time));
    }

    #[When(':analyticsApplication delivers the trade signal :alias')]
    public function deliversTheTradeSignal(string $analyticsApplication, string $alias): void
    {
        $this->deliver($analyticsApplication, $alias, $this->messageFor($alias, $analyticsApplication)->body());
    }

    #[When(':analyticsApplication delivers the trade signal :alias without :field')]
    public function deliversTheTradeSignalWithout(string $analyticsApplication, string $alias, string $field): void
    {
        $this->deliver($analyticsApplication, $alias, $this->messageFor($alias, $analyticsApplication)->without($field)->body());
    }

    #[When(':analyticsApplication delivers the trade signal :alias with :field set to :value')]
    public function deliversTheTradeSignalWith(string $analyticsApplication, string $alias, string $field, string $value): void
    {
        $this->deliver($analyticsApplication, $alias, $this->messageFor($alias, $analyticsApplication)->with($field, $value)->body());
    }

    #[When(':analyticsApplication redelivers the trade signal :alias')]
    public function redeliversTheTradeSignal(string $analyticsApplication, string $alias): void
    {
        $this->deliver($analyticsApplication, $alias, $this->bodies[$alias]);
    }

    /**
     * Runs one job of the PostgreSQL job queue. Each scenario delivers one trade signal, so that job is its evaluation;
     * the publication of a signal outcome it records stays queued, so scenarios need no RabbitMQ.
     */
    #[When('Trade Maneuver evaluates the trade signal :alias')]
    public function tradeManeuverEvaluatesTheTradeSignal(string $alias): void
    {
        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0]);
        Assert::assertSame(0, DB::table('failed_jobs')->count(), 'The evaluation of the trade signal "'.$alias.'" was expected not to fail.');
    }

    #[Then('the trade signal :alias ends with one SignalDeclined outcome with the reason :reason')]
    public function theTradeSignalEndsWithOneSignalDeclinedOutcome(string $alias, string $reason): void
    {
        $outcomes = $this->outcomesOf($alias);
        Assert::assertCount(1, $outcomes, 'Expected exactly one signal outcome in the outbox.');
        $payload = $outcomes[0]['payload'];
        Assert::assertSame('tm.signal-outcome.v1', $payload['type']);
        Assert::assertSame('trade-maneuver', $payload['source']);
        Assert::assertSame('SignalDeclined', $payload['outcome']);
        Assert::assertSame($reason, $payload['reason']);
        Assert::assertArrayNotHasKey('violations', $payload);
    }

    #[Then('the trade signal :alias ends with one SignalRejected outcome listing a violation of :field')]
    public function theTradeSignalEndsWithOneSignalRejectedOutcome(string $alias, string $field): void
    {
        $outcomes = $this->outcomesOf($alias);
        Assert::assertCount(1, $outcomes, 'Expected exactly one signal outcome in the outbox.');
        $payload = $outcomes[0]['payload'];
        Assert::assertSame('tm.signal-outcome.v1', $payload['type']);
        Assert::assertSame('trade-maneuver', $payload['source']);
        Assert::assertSame('SignalRejected', $payload['outcome']);
        Assert::assertContains($field, array_column($payload['violations'], 'field'));
        Assert::assertSame(TradeSignalIntakeResult::Accepted, $this->lastResult);
    }

    #[Then('the stored outcome of the trade signal :alias is recorded again with the same message identity')]
    public function theStoredOutcomeIsRecordedAgain(string $alias): void
    {
        $outcomes = $this->outcomesOf($alias);
        Assert::assertCount(2, $outcomes, 'Expected the stored signal outcome to be recorded once more.');
        Assert::assertSame($outcomes[0]['message_id'], $outcomes[1]['message_id']);
        Assert::assertSame($outcomes[0]['payload'], $outcomes[1]['payload']);
        Assert::assertSame(TradeSignalIntakeResult::Accepted, $this->lastResult);
    }

    #[Then('nothing new is processed for the trade signal :alias')]
    public function nothingNewIsProcessed(string $alias): void
    {
        $id = TradeSignalMessageBuilder::idFor($alias);
        Assert::assertSame(1, DB::table('trading_inbox_entries')->where('message_id', $id)->count());
        Assert::assertSame(1, DB::table('trading_trade_signals')->where('id', $id)->count());
        Assert::assertSame(1, DB::table('trading_audit_records')->where('entity_id', $id)->where('action', 'duplicate_delivered')->count());
    }

    #[Then('the trade signal :alias is stored and awaits evaluation')]
    public function theTradeSignalIsStoredAndAwaitsEvaluation(string $alias): void
    {
        $tradeSignal = DB::table('trading_trade_signals')->where('id', TradeSignalMessageBuilder::idFor($alias))->first();
        Assert::assertNotNull($tradeSignal, 'Expected the trade signal to be stored.');
        Assert::assertNull($tradeSignal->outcome);
        Assert::assertSame('DEMO_A', $tradeSignal->instrument_symbol);
        Assert::assertSame('DEMO', $tradeSignal->instrument_mic);
        Assert::assertSame('0.85', $tradeSignal->confidence);
        Assert::assertSame('12.50', $tradeSignal->reference_price_amount);
        Assert::assertSame(TradeSignalIntakeResult::Accepted, $this->lastResult);
    }

    #[Then('no signal outcome is recorded')]
    public function noSignalOutcomeIsRecorded(): void
    {
        Assert::assertSame(0, DB::table('trading_outbox_messages')->count());
    }

    private function messageFor(string $alias, string $analyticsApplication): TradeSignalMessageBuilder
    {
        return TradeSignalMessageBuilder::aTradeSignal(TradeSignalMessageBuilder::idFor($alias), $analyticsApplication);
    }

    private function deliver(string $analyticsApplication, string $alias, string $body): void
    {
        $this->bodies[$alias] = $body;
        $this->lastResult = $this->application->make(ReceiveTradeSignalCommandHandler::class)->handle(
            new ReceiveTradeSignalCommand($body, $this->rabbitMqUserOf($analyticsApplication)),
        );
    }

    private function rabbitMqUserOf(string $analyticsApplication): ?string
    {
        foreach ($this->registry as $entry) {
            if ($entry['id'] === $analyticsApplication) {
                return $entry['rabbitmq_user'];
            }
        }

        return null;
    }

    /**
     * @return list<array{message_id: string, payload: array<string, mixed>}>
     */
    private function outcomesOf(string $alias): array
    {
        $outcomes = [];
        $rows = DB::table('trading_outbox_messages')
            ->whereRaw("payload->>'signalId' = ?", [TradeSignalMessageBuilder::idFor($alias)])
            ->orderBy('id')
            ->get();
        foreach ($rows as $row) {
            $outcomes[] = [
                'message_id' => (string) $row->message_id,
                'payload' => json_decode((string) $row->payload, true, 512, JSON_THROW_ON_ERROR),
            ];
        }

        return $outcomes;
    }
}
