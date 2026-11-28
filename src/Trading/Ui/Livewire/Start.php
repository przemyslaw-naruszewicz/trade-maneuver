<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use TradeManeuver\Trading\Infrastructure\Order\ReadModel\OrderOverviewReadModel;

#[Layout('trading.components.layouts.panel')]
#[Title('Start')]
final class Start extends Component
{
    public function render(): View
    {
        $soonest = OrderOverviewReadModel::awaitingApproval()->first();

        return view('trading.livewire.start', [
            'awaitingApproval' => OrderOverviewReadModel::awaitingApproval()->count(),
            'otherNonTerminal' => OrderOverviewReadModel::otherNonTerminal()->count(),
            'soonest' => $soonest === null ? null : OrderAwaitingApprovalPresentation::of($soonest),
        ]);
    }
}
