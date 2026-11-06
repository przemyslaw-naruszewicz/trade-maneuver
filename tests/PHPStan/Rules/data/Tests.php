<?php

declare(strict_types=1);

namespace Tests\Fixture\Widget;

use TradeManeuver\Fixture\Domain\Widget\Widget;

final class WidgetTest
{
    public function test_state(Widget $widget): void
    {
        $widget->state();
    }
}
