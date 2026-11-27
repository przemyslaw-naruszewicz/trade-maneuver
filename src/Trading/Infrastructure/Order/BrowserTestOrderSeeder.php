<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Order;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Psr\Clock\ClockInterface;
use RuntimeException;
use TradeManeuver\Trading\Application\InvestmentConfiguration\Command\CreateInvestmentConfiguration\CreateInvestmentConfigurationCommand;
use TradeManeuver\Trading\Application\InvestmentConfiguration\Command\CreateInvestmentConfiguration\CreateInvestmentConfigurationCommandHandler;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommand;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommandHandler;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\TradeSignalIntakeResult;
use TradeManeuver\Trading\Application\Workflow\TradeSignalEvaluation\TradeSignalEvaluationOrchestrator;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

/**
 * Creates the data of the orders mockup for the browser tests through the application's use cases: the investment
 * configuration and the orders awaiting approval of three long trade signals of the demo analytics application.
 * bin/e2e runs it on the e2e database after the browser-test operator account. The signal validity of each order
 * ends long after a test run, in the order of the mockup.
 */
final class BrowserTestOrderSeeder extends Seeder
{
    private const string ANALYTICS_APPLICATION = 'demo-analytics';

    private const string INVESTMENT_CONFIGURATION = 'demo-eur';

    /**
     * The trade signals of the orders in orders.html, with the time from seeding until their validity ends.
     *
     * @var list<array{symbol: string, confidence: string, horizon: array<string, int|string>, expectedMove: array{min: string, max: string}|null, referencePrice: string, validFor: string}>
     */
    private const array TRADE_SIGNALS = [
        ['symbol' => 'DEMO_A', 'confidence' => '0.72', 'horizon' => ['sessions' => 5], 'expectedMove' => ['min' => '2.00', 'max' => '4.00'], 'referencePrice' => '10.40', 'validFor' => '+12 hours'],
        ['symbol' => 'DEMO_B', 'confidence' => '0.64', 'horizon' => ['sessions' => 10], 'expectedMove' => ['min' => '1.50', 'max' => '3.00'], 'referencePrice' => '6.20', 'validFor' => '+1 day'],
        ['symbol' => 'DEMO_C', 'confidence' => '0.58', 'horizon' => ['at' => '2026-11-28'], 'expectedMove' => null, 'referencePrice' => '24.80', 'validFor' => '+3 days'],
    ];

    public function run(
        CreateInvestmentConfigurationCommandHandler $createInvestmentConfiguration,
        ReceiveTradeSignalCommandHandler $receiveTradeSignal,
        TradeSignalEvaluationOrchestrator $evaluation,
        ClockInterface $clock,
    ): void {
        $createInvestmentConfiguration->handle(new CreateInvestmentConfigurationCommand(
            'seeder:'.self::class,
            self::INVESTMENT_CONFIGURATION,
            'EUR',
            'DEMO-ACC-001',
            '200.00',
            'EUR',
            '0.50',
            [self::ANALYTICS_APPLICATION],
        ));

        $seededAt = $clock->now()->setTimezone(new DateTimeZone('UTC'));
        foreach (self::TRADE_SIGNALS as $tradeSignal) {
            $id = Str::uuid7()->toString();
            $body = $this->messageBody($id, $tradeSignal, $seededAt);
            $result = $receiveTradeSignal->handle(new ReceiveTradeSignalCommand($body, self::ANALYTICS_APPLICATION));
            if ($result !== TradeSignalIntakeResult::Accepted) {
                throw new RuntimeException(sprintf('The trade signal of %s was not accepted.', $tradeSignal['symbol']));
            }
            // No queue worker runs in the e2e environment, so the evaluation runs here.
            $evaluation->evaluate(new TradeSignalId($id));
        }
    }

    /**
     * @param  array{symbol: string, confidence: string, horizon: array<string, int|string>, expectedMove: array{min: string, max: string}|null, referencePrice: string, validFor: string}  $tradeSignal
     */
    private function messageBody(string $id, array $tradeSignal, DateTimeImmutable $seededAt): string
    {
        $time = $seededAt->format('Y-m-d\TH:i:s\Z');
        $message = [
            'id' => $id,
            'source' => self::ANALYTICS_APPLICATION,
            'type' => 'tm.trade-signal.v1',
            'time' => $time,
            'investmentConfigurationRef' => self::INVESTMENT_CONFIGURATION,
            'instrument' => ['symbol' => $tradeSignal['symbol'], 'mic' => 'DEMO'],
            'direction' => 'long',
            'confidence' => $tradeSignal['confidence'],
            'horizon' => $tradeSignal['horizon'],
            'referencePrice' => ['amount' => $tradeSignal['referencePrice'], 'currency' => 'EUR', 'asOf' => $time],
            'validUntil' => $seededAt->modify($tradeSignal['validFor'])->format('Y-m-d\TH:i:s\Z'),
        ];
        if ($tradeSignal['expectedMove'] !== null) {
            $message['expectedMove'] = $tradeSignal['expectedMove'];
        }

        return json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
