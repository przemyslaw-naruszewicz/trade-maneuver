<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\Dto;

use DateTimeImmutable;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcome;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalContent;

/**
 * A rejected trade signal may have no content: its message did not satisfy the contract.
 */
final class TradeSignalStateDto
{
    public function __construct(
        public AnalyticsApplicationId $source,
        public DateTimeImmutable $receivedAt,
        public ?TradeSignalContent $content,
        public ?SignalOutcome $outcome,
    ) {}
}
