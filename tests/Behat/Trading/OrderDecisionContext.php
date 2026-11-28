<?php

declare(strict_types=1);

namespace Tests\Behat\Trading;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\BeforeScenario;
use Behat\Step\Then;
use Behat\Step\When;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Assert;
use Tests\Support\Trading\TradeSignalMessageBuilder;
use TradeManeuver\Trading\Application\Order\Command\ApproveOrder\ApproveOrderCommand;
use TradeManeuver\Trading\Application\Order\Command\ApproveOrder\ApproveOrderCommandHandler;

/**
 * The operator's decisions on orders awaiting approval, through their application commands. The trade signals and
 * their orders come from the steps of TradeSignalIntakeContext, whose application this context uses.
 */
final class OrderDecisionContext implements Context
{
    private const string OPERATOR_ID = '019a5a6e-0000-7000-8000-0000000000d1';

    private TradeSignalIntakeContext $intake;

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

    private function orderIdOf(string $alias): string
    {
        $orderId = DB::table('trading_orders')->where('trade_signal_id', TradeSignalMessageBuilder::idFor($alias))->value('id');
        Assert::assertIsString($orderId, sprintf('Expected an order for the trade signal "%s".', $alias));

        return $orderId;
    }
}
