<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Order\Support;

use Illuminate\Support\Facades\DB;
use TradeManeuver\Trading\Application\Order\Command\ApproveOrder\ApproveOrderCommand;
use TradeManeuver\Trading\Application\Order\Command\ApproveOrder\ApproveOrderCommandHandler;
use TradeManeuver\Trading\Application\Order\Command\RefuseOrder\RefuseOrderCommand;
use TradeManeuver\Trading\Application\Order\Command\RefuseOrder\RefuseOrderCommandHandler;

/**
 * Decides on the orders that OrdersAwaitingApproval stores, through the application commands, as the operator of
 * OperatorAccounts.
 */
trait OrderDecisions
{
    private function orderIdOf(string $symbol): string
    {
        $orderId = DB::table('trading_orders')->where('instrument_symbol', $symbol)->orderByDesc('created_at')->value('id');
        $this->assertIsString($orderId, sprintf('Expected an order of %s.', $symbol));

        return $orderId;
    }

    private function approveOrderOf(string $symbol, ?string $newLimitPrice = null): void
    {
        $this->app->make(ApproveOrderCommandHandler::class)->handle(new ApproveOrderCommand(
            $this->orderIdOf($symbol),
            $this->operatorModel()->id,
            $newLimitPrice,
            $newLimitPrice === null ? null : 'EUR',
        ));
    }

    private function refuseOrderOf(string $symbol): void
    {
        $this->app->make(RefuseOrderCommandHandler::class)->handle(new RefuseOrderCommand($this->orderIdOf($symbol), $this->operatorModel()->id));
    }
}
