<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\Livewire;

use DateTimeZone;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Psr\Clock\ClockInterface;
use TradeManeuver\Trading\Infrastructure\Order\ReadModel\OrderAwaitingApprovalReadModel;

#[Layout('trading.components.layouts.panel')]
#[Title('Orders')]
final class Orders extends Component
{
    public function render(ClockInterface $clock): View
    {
        // The time of this render is the time of the last successful update that the browser shows.
        $updatedAt = $clock->now()->setTimezone(new DateTimeZone('UTC'))->format('H:i:s');
        $orders = [];
        foreach (OrderAwaitingApprovalReadModel::soonestValidUntilFirst()->get() as $order) {
            $orders[] = OrderAwaitingApprovalPresentation::of($order);
        }

        return view('trading.livewire.orders', ['orders' => $orders, 'updatedAt' => $updatedAt]);
    }
}
