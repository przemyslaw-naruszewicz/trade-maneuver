<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use TradeManeuver\Trading\Infrastructure\Order\ReadModel\OrderOverviewReadModel;

/**
 * The number of orders awaiting approval in the Orders entry of the panel navigation. It is a component of its own,
 * so that its updates leave the rest of the page as it is.
 */
final class OrdersAwaitingApprovalCount extends Component
{
    public function render(): View
    {
        return view('trading.livewire.orders-awaiting-approval-count', [
            'count' => OrderOverviewReadModel::awaitingApproval()->count(),
        ]);
    }
}
