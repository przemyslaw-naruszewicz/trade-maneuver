<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Order;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Trading\Operator\Support\OperatorAccounts;
use Tests\Feature\Trading\Order\Support\OrderChangedOnSave;
use Tests\Feature\Trading\Order\Support\OrderDecisions;
use Tests\Feature\Trading\Order\Support\OrdersAwaitingApproval;
use Tests\TestCase;
use Tests\Unit\Trading\TradeSignal\Support\TradeSignalBuilder;
use Throwable;
use TradeManeuver\Trading\Application\Order\Command\RefuseOrder\RefuseOrderCommand;
use TradeManeuver\Trading\Application\Order\Command\RefuseOrder\RefuseOrderCommandHandler;
use TradeManeuver\Trading\Application\Order\Exception\OrderAlreadyDecidedException;
use TradeManeuver\Trading\Application\Workflow\SignalOutcomeRecording\SignalOutcomeRecordingOrchestrator;
use TradeManeuver\Trading\Domain\Order\Exception\OrderNotAwaitingApprovalException;
use TradeManeuver\Trading\Domain\Order\Repository\Exception\OrderChangedConcurrentlyException;
use TradeManeuver\Trading\Domain\Order\Repository\OrderRepositoryInterface;
use TradeManeuver\Trading\Domain\TradeSignal\Exception\InvalidTradeSignalStateException;
use TradeManeuver\Trading\Domain\TradeSignal\Repository\TradeSignalRepositoryInterface;
use TradeManeuver\Trading\Domain\TradeSignal\TradeSignal;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcomeId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;
use TradeManeuver\Trading\Infrastructure\Messaging\Jobs\RecordSignalOutcomeJob;

/**
 * The approve and refuse use cases and the recording of the signal outcome after a refusal, against PostgreSQL: what
 * they audit and log, and how a decision that comes too late is refused.
 */
final class OrderDecisionCommandsTest extends TestCase
{
    use OperatorAccounts;
    use OrderDecisions;
    use OrdersAwaitingApproval;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-09T11:00:00Z'));
        $this->createEnrolledOperator();
        $this->addTheInvestmentConfiguration();
        $this->addOrder('DEMO_A', '2026-11-10T16:00:00Z');
    }

    public function test_an_approval_audits_the_previous_and_the_new_limit_price_and_quantity_and_is_logged(): void
    {
        $orderId = $this->orderIdOf('DEMO_A');
        $log = Log::spy();

        $this->approveOrderOf('DEMO_A', '10.50');

        $this->assertSame([
            'currency' => 'EUR',
            'limitPrice' => '10.50',
            'previousLimitPrice' => '10.20',
            'previousQuantity' => '98',
            'quantity' => '95',
        ], $this->auditedValues($orderId, 'approved'));
        $log->shouldHaveReceived('info', ['The operator approved an order.', ['orderId' => $orderId, 'signalId' => $this->tradeSignalIdOf($orderId)]])->once();
    }

    public function test_a_refusal_audits_the_unchanged_limit_price_and_quantity_with_the_note_queues_the_signal_outcome_and_is_logged(): void
    {
        $orderId = $this->orderIdOf('DEMO_A');
        $log = Log::spy();

        $this->app->make(RefuseOrderCommandHandler::class)->handle(new RefuseOrderCommand($orderId, $this->operatorModel()->id, 'The spread looks too wide today.'));

        $this->assertSame([
            'currency' => 'EUR',
            'limitPrice' => '10.20',
            'note' => 'The spread looks too wide today.',
            'previousLimitPrice' => '10.20',
            'previousQuantity' => '98',
            'quantity' => '98',
        ], $this->auditedValues($orderId, 'refused'));
        $this->assertSame(RecordSignalOutcomeJob::class, json_decode((string) DB::table('jobs')->sole()->payload, true, 512, JSON_THROW_ON_ERROR)['displayName']);
        $log->shouldHaveReceived('info', ['The operator refused an order.', ['orderId' => $orderId, 'signalId' => $this->tradeSignalIdOf($orderId)]])->once();
    }

    public function test_a_refusal_without_a_note_audits_no_note(): void
    {
        $orderId = $this->orderIdOf('DEMO_A');

        $this->refuseOrderOf('DEMO_A');

        $this->assertArrayNotHasKey('note', $this->auditedValues($orderId, 'refused'));
    }

    public function test_a_refusal_of_an_order_that_is_no_longer_awaiting_approval_is_refused_as_already_decided(): void
    {
        $this->approveOrderOf('DEMO_A');

        $refusal = $this->refusalOf(fn () => $this->refuseOrderOf('DEMO_A'));

        $this->assertInstanceOf(OrderAlreadyDecidedException::class, $refusal);
        $this->assertInstanceOf(OrderNotAwaitingApprovalException::class, $refusal->getPrevious());
        $this->assertSame('approved', DB::table('trading_orders')->value('status'));
        $this->assertSame(0, DB::table('jobs')->count());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function decisions(): iterable
    {
        yield 'approval' => ['approve'];
        yield 'refusal' => ['refuse'];
    }

    #[DataProvider('decisions')]
    public function test_a_decision_on_an_order_that_changed_after_it_was_loaded_is_refused_as_already_decided(string $decision): void
    {
        $this->app->extend(OrderRepositoryInterface::class, fn (OrderRepositoryInterface $orders): OrderRepositoryInterface => new OrderChangedOnSave($orders));

        $refusal = $this->refusalOf(fn () => $decision === 'approve' ? $this->approveOrderOf('DEMO_A') : $this->refuseOrderOf('DEMO_A'));

        $this->assertInstanceOf(OrderAlreadyDecidedException::class, $refusal);
        $this->assertInstanceOf(OrderChangedConcurrentlyException::class, $refusal->getPrevious());
        $this->assertSame('awaiting_approval', DB::table('trading_orders')->value('status'));
        $this->assertSame(0, DB::table('trading_audit_records')->whereIn('action', ['approved', 'refused'])->count());
    }

    public function test_the_recording_step_declines_the_trade_signal_of_a_refused_order_once(): void
    {
        $orderId = $this->orderIdOf('DEMO_A');
        $tradeSignalId = $this->tradeSignalIdOf($orderId);
        $this->refuseOrderOf('DEMO_A');
        $this->travelTo(CarbonImmutable::parse('2026-11-09T11:00:05Z'));
        $log = Log::spy();

        $this->recordSignalOutcomeOf($orderId);
        $this->recordSignalOutcomeOf($orderId);

        $tradeSignal = DB::table('trading_trade_signals')->where('id', $tradeSignalId)->sole();
        $this->assertSame('SignalDeclined', $tradeSignal->outcome);
        $this->assertSame('RefusedByOperator', $tradeSignal->outcome_reason);
        $this->assertSame('2026-11-09 11:00:05+00', $tradeSignal->outcome_occurred_at);
        $payload = json_decode((string) DB::table('trading_outbox_messages')->sole()->payload, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([$tradeSignalId, 'SignalDeclined', 'RefusedByOperator'], [$payload['signalId'], $payload['outcome'], $payload['reason']]);
        $this->assertSame(1, DB::table('trading_audit_records')->where('entity_id', $tradeSignalId)->where('action', 'declined')->count());
        $log->shouldHaveReceived('info', ['A trade signal is declined.', ['signalId' => $tradeSignalId, 'outcomeId' => $tradeSignal->outcome_id, 'reason' => 'RefusedByOperator']])->once();
        $log->shouldHaveReceived('info', ['The trade signal of an ended order already has its signal outcome.', ['orderId' => $orderId, 'signalId' => $tradeSignalId]])->once();
    }

    public function test_the_recording_step_refuses_an_order_that_has_not_ended(): void
    {
        $orderId = $this->orderIdOf('DEMO_A');
        $this->approveOrderOf('DEMO_A');

        $refusal = $this->refusalOf(fn () => $this->recordSignalOutcomeOf($orderId));

        $this->assertInstanceOf(InvalidTradeSignalStateException::class, $refusal);
        $this->assertNull(DB::table('trading_trade_signals')->value('outcome'));
        $this->assertSame(0, DB::table('trading_outbox_messages')->count());
    }

    public function test_the_recording_step_refuses_a_trade_signal_that_records_more_than_its_decline(): void
    {
        $orderId = $this->orderIdOf('DEMO_A');
        $this->refuseOrderOf('DEMO_A');
        // The repository hands out a trade signal that still holds its TradeSignalReceived event.
        $this->app->instance(TradeSignalRepositoryInterface::class, new class implements TradeSignalRepositoryInterface
        {
            public function nextOutcomeIdentity(): SignalOutcomeId
            {
                return new SignalOutcomeId(TradeSignalBuilder::OUTCOME_ID);
            }

            public function add(TradeSignal $tradeSignal): void {}

            public function get(TradeSignalId $id): TradeSignal
            {
                return TradeSignalBuilder::received();
            }

            public function getLocked(TradeSignalId $id): TradeSignal
            {
                return TradeSignalBuilder::received();
            }

            public function save(TradeSignal $tradeSignal): void {}
        });

        $refusal = $this->refusalOf(fn () => $this->recordSignalOutcomeOf($orderId));

        $this->assertInstanceOf(LogicException::class, $refusal);
        $this->assertStringContainsString('TradeSignalReceived', $refusal->getMessage());
        $this->assertSame(0, DB::table('trading_outbox_messages')->count());
        $this->assertSame(0, DB::table('trading_audit_records')->where('action', 'declined')->count());
    }

    private function recordSignalOutcomeOf(string $orderId): void
    {
        (new RecordSignalOutcomeJob($orderId))->handle($this->app->make(SignalOutcomeRecordingOrchestrator::class));
    }

    private function refusalOf(callable $decision): ?Throwable
    {
        try {
            $decision();
        } catch (Throwable $caught) {
            return $caught;
        }

        return null;
    }

    private function tradeSignalIdOf(string $orderId): string
    {
        return (string) DB::table('trading_orders')->where('id', $orderId)->value('trade_signal_id');
    }

    /**
     * The values of the order's audit record of the action, by key.
     *
     * @return array<string, mixed>
     */
    private function auditedValues(string $orderId, string $action): array
    {
        $values = json_decode((string) DB::table('trading_audit_records')->where('entity_id', $orderId)->where('action', $action)->sole()->values, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($values);
        ksort($values);

        return $values;
    }
}
