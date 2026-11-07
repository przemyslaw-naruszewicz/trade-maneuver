<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\Event;

use DateTimeImmutable;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalContent;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

final readonly class TradeSignalReceived
{
    public function __construct(
        public TradeSignalId $id,
        public AnalyticsApplicationId $source,
        public TradeSignalContent $content,
        public DateTimeImmutable $receivedAt,
    ) {}
}
