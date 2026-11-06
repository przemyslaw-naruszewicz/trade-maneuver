<?php

declare(strict_types=1);

namespace TradeManeuver\Fixture\Application\Widget;

use TradeManeuver\Fixture\Domain\Widget\Widget;
use TradeManeuver\Fixture\Infrastructure\Widget\Machine;

final class WidgetHandler
{
    public function handle(Widget $widget, ?Widget $maybeWidget, Machine $machine): void
    {
        $widget->state();
        $maybeWidget?->state();
        $widget->state(...);
        $widget->STATE();
        $machine->state();
    }
}

function readWidgetState(Widget $widget): void
{
    $widget->state();
}
