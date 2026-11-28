<?php

declare(strict_types=1);

namespace Tests\Behat\Trading;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\BeforeScenario;
use Behat\Step\Then;
use Behat\Step\When;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Assert;
use Tests\Support\Trading\TradeSignalMessageBuilder;
use Throwable;
use TradeManeuver\Trading\Application\Order\Command\ApproveOrder\ApproveOrderCommand;
use TradeManeuver\Trading\Application\Order\Command\ApproveOrder\ApproveOrderCommandHandler;
use TradeManeuver\Trading\Application\Order\Command\RefuseOrder\RefuseOrderCommand;
use TradeManeuver\Trading\Application\Order\Command\RefuseOrder\RefuseOrderCommandHandler;
use TradeManeuver\Trading\Domain\Order\Exception\ApprovalLeavesZeroQuantityException;

/**
 * The operator's decisions on orders awaiting approval, through their application commands. The trade signals and
 * their orders come from the steps of TradeSignalIntakeContext, whose application this context uses.
 */
final class OrderDecisionContext implements Context
{
    private const string OPERATOR_ID = '019a5a6e-0000-7000-8000-0000000000d1';

    private TradeSignalIntakeContext $intake;

    private ?Throwable $refusal = null;

    #[BeforeScenario]
    public function gatherContexts(BeforeScenarioScope $scope): void
    {
        $environment = $scope->getEnvironment();
        assert(method_exists($environment, 'getContext'));
        $this->intake = $environment->getContext(TradeSignalIntakeContext::class);
    }

    #[When('the operator approves the order of the trade signal :alias without a new limit price')]
    public function theOperatorApprovesWithoutANewLimitPrice(string $alias): void
    {
        $this->intake->application()->make(ApproveOrderCommandHandler::class)
            ->handle(new ApproveOrderCommand($this->orderIdOf($alias), self::OPERATOR_ID));
    }

    #[When('the operator approves the order of the trade signal :alias with the new limit price :limitPrice EUR')]
    public function theOperatorApprovesWithANewLimitPrice(string $alias, string $limitPrice): void
    {
        $this->intake->application()->make(ApproveOrderCommandHandler::class)
            ->handle(new ApproveOrderCommand($this->orderIdOf($alias), self::OPERATOR_ID, $limitPrice));
    }

    #[When('the operator tries to approve the order of the trade signal :alias with the new limit price :limitPrice EUR')]
    public function theOperatorTriesToApproveWithANewLimitPrice(string $alias, string $limitPrice): void
    {
        try {
            $this->theOperatorApprovesWithANewLimitPrice($alias, $limitPrice);
        } catch (Throwable $refusal) {
            $this->refusal = $refusal;
        }
    }

    #[Then('the approval is refused because the new limit price leaves a quantity of zero')]
    public function theApprovalIsRefusedForAQuantityOfZero(): void
    {
        Assert::assertInstanceOf(ApprovalLeavesZeroQuantityException::class, $this->refusal);
    }

    #[Then('the order of the trade signal :alias still awaits approval with the limit price :limitPrice EUR and the quantity :quantity')]
    public function theOrderStillAwaitsApproval(string $alias, string $limitPrice, int $quantity): void
    {
        $order = DB::table('trading_orders')->where('trade_signal_id', TradeSignalMessageBuilder::idFor($alias))->sole();
        Assert::assertSame('awaiting_approval', $order->status);
        Assert::assertSame($limitPrice, $order->limit_price);
        Assert::assertSame($quantity, $order->quantity);
        Assert::assertSame(0, DB::table('trading_audit_records')->where('entity_id', $order->id)->where('action', 'approved')->count());
    }

    #[When('the operator refuses the order of the trade signal :alias with the note :note')]
    public function theOperatorRefusesWithANote(string $alias, string $note): void
    {
        $this->intake->application()->make(RefuseOrderCommandHandler::class)
            ->handle(new RefuseOrderCommand($this->orderIdOf($alias), self::OPERATOR_ID, $note));
    }

    /**
     * Runs one job of the PostgreSQL job queue: the step that the refusal queued.
     */
    #[When('Trade Maneuver records the signal outcome of the trade signal :alias')]
    public function tradeManeuverRecordsTheSignalOutcome(string $alias): void
    {
        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0]);
        Assert::assertSame(0, DB::table('failed_jobs')->count(), 'Recording the signal outcome of the trade signal "'.$alias.'" was expected not to fail.');
    }

    #[Then('the order of the trade signal :alias is refused by the operator')]
    public function theOrderIsRefused(string $alias): void
    {
        $order = DB::table('trading_orders')->where('trade_signal_id', TradeSignalMessageBuilder::idFor($alias))->sole();
        Assert::assertSame('refused_by_operator', $order->status);
        Assert::assertSame('2026-11-09 11:00:00+00', $order->ended_at);
    }

    #[Then('the refusal of the order of the trade signal :alias is audited with the note :note')]
    public function theRefusalIsAuditedWithTheNote(string $alias, string $note): void
    {
        $audit = DB::table('trading_audit_records')->where('entity_type', 'order')->where('entity_id', $this->orderIdOf($alias))->where('action', 'refused')->sole();
        Assert::assertSame('operator:'.self::OPERATOR_ID, $audit->actor);
        Assert::assertSame('2026-11-09 11:00:00+00', $audit->occurred_at);
        Assert::assertEquals([
            'previousLimitPrice' => '10.20',
            'limitPrice' => '10.20',
            'currency' => 'EUR',
            'previousQuantity' => '98',
            'quantity' => '98',
            'note' => $note,
        ], json_decode((string) $audit->values, true, 512, JSON_THROW_ON_ERROR));
    }

    #[Then('the signal outcome of the trade signal :alias does not contain the note :note')]
    public function theSignalOutcomeDoesNotContainTheNote(string $alias, string $note): void
    {
        $payloads = DB::table('trading_outbox_messages')->whereRaw("payload->>'signalId' = ?", [TradeSignalMessageBuilder::idFor($alias)])->pluck('payload');
        Assert::assertCount(1, $payloads);
        Assert::assertStringNotContainsString($note, (string) $payloads[0]);
        $decline = DB::table('trading_audit_records')->where('entity_type', 'trade_signal')->where('action', 'declined')->sole();
        Assert::assertStringNotContainsString($note, (string) $decline->values);
    }

    #[Then('the order of the trade signal :alias is approved with the limit price :limitPrice EUR and the quantity :quantity')]
    public function theOrderIsApproved(string $alias, string $limitPrice, int $quantity): void
    {
        $order = DB::table('trading_orders')->where('trade_signal_id', TradeSignalMessageBuilder::idFor($alias))->sole();
        Assert::assertSame('approved', $order->status);
        Assert::assertSame($limitPrice, $order->limit_price);
        Assert::assertSame('EUR', $order->currency);
        Assert::assertSame($quantity, $order->quantity);
        Assert::assertSame('1000.00', $order->order_amount);
        Assert::assertSame('DEMO_A', $order->instrument_symbol);
    }

    #[Then('the approval of the order of the trade signal :alias is audited with the limit price changed from :previousLimitPrice to :limitPrice EUR and the quantity from :previousQuantity to :quantity')]
    public function theApprovalIsAudited(string $alias, string $previousLimitPrice, string $limitPrice, string $previousQuantity, string $quantity): void
    {
        $audit = DB::table('trading_audit_records')->where('entity_type', 'order')->where('entity_id', $this->orderIdOf($alias))->where('action', 'approved')->sole();
        Assert::assertSame('operator:'.self::OPERATOR_ID, $audit->actor);
        Assert::assertSame('2026-11-09 11:00:00+00', $audit->occurred_at);
        // PostgreSQL stores the values as jsonb, which orders their keys its own way.
        Assert::assertEquals([
            'previousLimitPrice' => $previousLimitPrice,
            'limitPrice' => $limitPrice,
            'currency' => 'EUR',
            'previousQuantity' => $previousQuantity,
            'quantity' => $quantity,
        ], json_decode((string) $audit->values, true, 512, JSON_THROW_ON_ERROR));
    }

    private function orderIdOf(string $alias): string
    {
        $orderId = DB::table('trading_orders')->where('trade_signal_id', TradeSignalMessageBuilder::idFor($alias))->value('id');
        Assert::assertIsString($orderId, sprintf('Expected an order for the trade signal "%s".', $alias));

        return $orderId;
    }
}
