<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('trading.components.layouts.panel')]
#[Title('Start')]
final class Start extends Component
{
    public function render(): View
    {
        return view('trading.livewire.start');
    }
}
