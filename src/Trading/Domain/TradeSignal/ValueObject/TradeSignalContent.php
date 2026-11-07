<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\ValueObject;

use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationRef;
use TradeManeuver\Trading\Domain\TradeSignal\Enum\Direction;

/**
 * What a valid trade signal says, as the analytics application sent it. Its `extensions`, which Trade Maneuver does
 * not interpret, stay in the message body that the inbox keeps.
 */
final readonly class TradeSignalContent
{
    public function __construct(
        public UtcTimestamp $issuedAt,
        public InvestmentConfigurationRef $investmentConfigurationRef,
        public Instrument $instrument,
        public Direction $direction,
        public Confidence $confidence,
        public Horizon $horizon,
        public ?ExpectedMove $expectedMove,
        public ReferencePrice $referencePrice,
        public UtcTimestamp $validUntil,
    ) {}
}
