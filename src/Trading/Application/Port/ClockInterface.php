<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Port;

use DateTimeImmutable;

interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
