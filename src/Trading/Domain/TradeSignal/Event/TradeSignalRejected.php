<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\Event;

use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcome;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

final readonly class TradeSignalRejected
{
    public function __construct(
        public TradeSignalId $id,
        public AnalyticsApplicationId $source,
        public SignalOutcome $outcome,
    ) {}
}
