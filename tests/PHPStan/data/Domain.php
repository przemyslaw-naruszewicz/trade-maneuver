<?php

declare(strict_types=1);

namespace TradeManeuver\Fixture\Domain\Widget;

final class Widget
{
    public function state(): WidgetStateDto
    {
        return new WidgetStateDto;
    }

    public function stateCopy(): WidgetStateDto
    {
        return $this->state();
    }
}

final class WidgetStateDto {}

interface WidgetRepositoryInterface
{
    public function add(Widget $widget): void;
}
