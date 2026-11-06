<?php

declare(strict_types=1);

namespace TradeManeuver\Fixture\Infrastructure\Widget;

use TradeManeuver\Fixture\Domain\Widget\Widget;
use TradeManeuver\Fixture\Domain\Widget\WidgetRepositoryInterface;

final class WidgetRepository implements WidgetRepositoryInterface
{
    public function add(Widget $widget): void
    {
        $widget->state();
    }
}

final class WidgetQuery
{
    public function find(Widget $widget): void
    {
        $widget->state();
    }
}

/**
 * Not a Domain class, so its state() is not the aggregate state contract.
 */
final class Machine
{
    public function state(): string
    {
        return 'idle';
    }
}
