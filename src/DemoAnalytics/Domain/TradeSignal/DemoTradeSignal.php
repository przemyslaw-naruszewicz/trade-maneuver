<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Domain\TradeSignal;

use TradeManeuver\DemoAnalytics\Domain\TradeSignal\Enum\Direction;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\Exception\InvalidTradeSignalException;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject\Confidence;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject\DemoInstrument;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject\ExpectedMove;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject\Horizon;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject\InvestmentConfigurationRef;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject\ReferencePrice;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject\UtcTime;

/**
 * A trade signal the demo analytics application issues on a fictional instrument, with invented values. It comes from
 * no analytical model and recommends nothing.
 */
final readonly class DemoTradeSignal
{
    /**
     * @throws InvalidTradeSignalException
     */
    public function __construct(
        public string $id,
        public UtcTime $issuedAt,
        public InvestmentConfigurationRef $investmentConfigurationRef,
        public DemoInstrument $instrument,
        public Direction $direction,
        public Confidence $confidence,
        public Horizon $horizon,
        public ExpectedMove $expectedMove,
        public ReferencePrice $referencePrice,
        public UtcTime $validUntil,
    ) {
        if (! $validUntil->isAfter($issuedAt)) {
            throw new InvalidTradeSignalException('The signal validity must end after the trade signal is issued.');
        }
    }
}
