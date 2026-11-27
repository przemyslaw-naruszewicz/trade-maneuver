<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use TradeManeuver\Trading\Infrastructure\Order\ReadModel\OrderAwaitingApprovalReadModel;

#[Layout('trading.components.layouts.panel')]
#[Title('Orders')]
final class Orders extends Component
{
    public function render(): View
    {
        $orders = [];
        foreach (OrderAwaitingApprovalReadModel::soonestValidUntilFirst()->get() as $order) {
            $orders[] = OrderAwaitingApprovalPresentation::of($order);
        }

        return view('trading.livewire.orders', ['orders' => $orders]);
    }
}
