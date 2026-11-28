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
use TradeManeuver\Trading\Application\Order\Command\ApproveOrder\ApproveOrderCommand;
use TradeManeuver\Trading\Application\Order\Command\ApproveOrder\ApproveOrderCommandHandler;
use TradeManeuver\Trading\Application\Order\Command\RefuseOrder\RefuseOrderCommand;
use TradeManeuver\Trading\Application\Order\Command\RefuseOrder\RefuseOrderCommandHandler;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommand;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommandHandler;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\TradeSignalIntakeResult;
use TradeManeuver\Trading\Application\Workflow\SignalOutcomeRecording\SignalOutcomeRecordingOrchestrator;
use TradeManeuver\Trading\Application\Workflow\TradeSignalEvaluation\TradeSignalEvaluationOrchestrator;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;
use TradeManeuver\Trading\Infrastructure\Operator\BrowserTestOperatorSeeder;
use TradeManeuver\Trading\Infrastructure\Operator\Eloquent\OperatorModel;
use TradeManeuver\Trading\Infrastructure\Order\Eloquent\OrderModel;

/**
 * Creates the data of the orders mockup for the browser tests through the application's use cases: the investment
 * configuration, the orders awaiting approval of three long trade signals of the demo analytics application, and the
 * orders of two further trade signals that the browser-test operator approves and refuses. bin/e2e runs it on the e2e
 * database after the browser-test operator account. The signal validity of each order ends long after a test run, in
 * the order of the mockup.
 */
final class BrowserTestOrderSeeder extends Seeder
{
    private const string ANALYTICS_APPLICATION = 'demo-analytics';

    private const string INVESTMENT_CONFIGURATION = 'demo-eur';

    /**
     * The trade signals of the orders in orders.html, with the time from seeding until their validity ends. A horizon
     * date is given as the time from seeding, so that it never lies in the past.
     *
     * @var list<array{symbol: string, confidence: string, horizon: array{sessions: int}|array{dateIn: string}, expectedMove: array{min: string, max: string}|null, referencePrice: string, validFor: string}>
     */
    private const array TRADE_SIGNALS = [
        ['symbol' => 'DEMO_A', 'confidence' => '0.72', 'horizon' => ['sessions' => 5], 'expectedMove' => ['min' => '2.00', 'max' => '4.00'], 'referencePrice' => '10.40', 'validFor' => '+12 hours'],
        ['symbol' => 'DEMO_B', 'confidence' => '0.64', 'horizon' => ['sessions' => 10], 'expectedMove' => ['min' => '1.50', 'max' => '3.00'], 'referencePrice' => '6.20', 'validFor' => '+1 day'],
        ['symbol' => 'DEMO_C', 'confidence' => '0.58', 'horizon' => ['dateIn' => '+3 weeks'], 'expectedMove' => null, 'referencePrice' => '24.80', 'validFor' => '+3 days'],
    ];

    /**
     * The trade signal of the approved order in orders.html, whose limit price the operator changes to 11.05 EUR.
     *
     * @var array{symbol: string, confidence: string, horizon: array{sessions: int}, expectedMove: null, referencePrice: string, validFor: string}
     */
    private const array APPROVED_TRADE_SIGNAL = ['symbol' => 'DEMO_A', 'confidence' => '0.70', 'horizon' => ['sessions' => 5], 'expectedMove' => null, 'referencePrice' => '10.90', 'validFor' => '+2 days'];

    private const string APPROVED_LIMIT_PRICE = '11.05';

    /**
     * The trade signal of the refused order in orders.html.
     *
     * @var array{symbol: string, confidence: string, horizon: array{sessions: int}, expectedMove: null, referencePrice: string, validFor: string}
     */
    private const array REFUSED_TRADE_SIGNAL = ['symbol' => 'DEMO_C', 'confidence' => '0.55', 'horizon' => ['sessions' => 5], 'expectedMove' => null, 'referencePrice' => '23.99', 'validFor' => '+2 days'];

    public function __construct(
        private readonly ReceiveTradeSignalCommandHandler $receiveTradeSignal,
        private readonly TradeSignalEvaluationOrchestrator $evaluation,
    ) {}

    public function run(
        CreateInvestmentConfigurationCommandHandler $createInvestmentConfiguration,
        ApproveOrderCommandHandler $approveOrder,
        RefuseOrderCommandHandler $refuseOrder,
        SignalOutcomeRecordingOrchestrator $signalOutcomeRecording,
        ClockInterface $clock,
    ): void {
        $operatorId = OperatorModel::query()->where('email', BrowserTestOperatorSeeder::EMAIL)->value('id');
        if (! is_string($operatorId)) {
            throw new RuntimeException('The browser-test operator account decides on the seeded orders, so it is seeded first.');
        }

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
            $this->orderOf($tradeSignal, $seededAt);
        }

        $approveOrder->handle(new ApproveOrderCommand(
            $this->orderOf(self::APPROVED_TRADE_SIGNAL, $seededAt),
            $operatorId,
            self::APPROVED_LIMIT_PRICE,
        ));
        $refusedOrderId = $this->orderOf(self::REFUSED_TRADE_SIGNAL, $seededAt);
        $refuseOrder->handle(new RefuseOrderCommand($refusedOrderId, $operatorId));
        // No queue worker runs in the e2e environment, so the signal outcome is recorded here; the queued recording
        // would skip the trade signal, which then has its signal outcome.
        $signalOutcomeRecording->record(new OrderId($refusedOrderId));
    }

    /**
     * Receives and evaluates a trade signal, and returns the ID of the order that its evaluation creates.
     *
     * @param  array{symbol: string, confidence: string, horizon: array{sessions: int}|array{dateIn: string}, expectedMove: array{min: string, max: string}|null, referencePrice: string, validFor: string}  $tradeSignal
     */
    private function orderOf(array $tradeSignal, DateTimeImmutable $seededAt): string
    {
        $id = Str::uuid7()->toString();
        $body = $this->messageBody($id, $tradeSignal, $seededAt);
        $result = $this->receiveTradeSignal->handle(new ReceiveTradeSignalCommand($body, self::ANALYTICS_APPLICATION));
        if ($result !== TradeSignalIntakeResult::Accepted) {
            throw new RuntimeException(sprintf('The trade signal of %s was not accepted.', $tradeSignal['symbol']));
        }
        // No queue worker runs in the e2e environment, so the evaluation runs here.
        $this->evaluation->evaluate(new TradeSignalId($id));

        $orderId = OrderModel::query()->where('trade_signal_id', $id)->value('id');
        if (! is_string($orderId)) {
            throw new RuntimeException(sprintf('The trade signal of %s created no order.', $tradeSignal['symbol']));
        }

        return $orderId;
    }

    /**
     * @param  array{symbol: string, confidence: string, horizon: array{sessions: int}|array{dateIn: string}, expectedMove: array{min: string, max: string}|null, referencePrice: string, validFor: string}  $tradeSignal
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
            'horizon' => isset($tradeSignal['horizon']['dateIn'])
                ? ['at' => $seededAt->modify($tradeSignal['horizon']['dateIn'])->format('Y-m-d')]
                : $tradeSignal['horizon'],
            'referencePrice' => ['amount' => $tradeSignal['referencePrice'], 'currency' => 'EUR', 'asOf' => $time],
            'validUntil' => $seededAt->modify($tradeSignal['validFor'])->format('Y-m-d\TH:i:s\Z'),
        ];
        if ($tradeSignal['expectedMove'] !== null) {
            $message['expectedMove'] = $tradeSignal['expectedMove'];
        }

        return json_encode($message, JSON_THROW_ON_ERROR);
    }
}
