<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\TradeSignal\Support;

use DateTimeImmutable;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationRef;
use TradeManeuver\Trading\Domain\TradeSignal\Enum\Direction;
use TradeManeuver\Trading\Domain\TradeSignal\TradeSignal;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Confidence;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ContractViolation;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ContractViolations;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ExpectedMove;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Extensions;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Horizon;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Instrument;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Mic;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ReferencePrice;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcomeId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalContent;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\UtcTimestamp;

/**
 * Creates trade signals on the fictional instrument DEMO_A at the fictional exchange DEMO.
 */
final class TradeSignalBuilder
{
    public const string ID = '019a5a6e-0000-7000-8000-0000000000a1';

    public const string OUTCOME_ID = '019a5a6e-0000-7000-8000-0000000000b1';

    public const string RECEIVED_AT = '2026-11-07T09:30:00.250000Z';

    public static function content(): TradeSignalContent
    {
        return new TradeSignalContent(
            UtcTimestamp::fromString('2026-11-07T09:00:00Z'),
            new InvestmentConfigurationRef('demo-investment'),
            new Instrument('DEMO_A', new Mic('DEMO')),
            Direction::Long,
            Confidence::of('0.85'),
            Horizon::of(5, '2026-11-20'),
            ExpectedMove::of('1.5', '4.0'),
            ReferencePrice::of('12.50', 'EUR', '2026-11-07T08:59:00Z'),
            UtcTimestamp::fromString('2026-11-10T16:00:00Z'),
            Extensions::of(['alertLevel' => 'amber']),
        );
    }

    public static function received(): TradeSignal
    {
        return TradeSignal::receive(
            new TradeSignalId(self::ID),
            new AnalyticsApplicationId('demo-analytics'),
            self::content(),
            new DateTimeImmutable(self::RECEIVED_AT),
        );
    }

    public static function violations(): ContractViolations
    {
        return ContractViolations::of(
            new ContractViolation('instrument.mic', 'The exchange code must have the MIC format: four upper-case letters or digits.'),
            new ContractViolation('confidence', 'The confidence must be a decimal string from 0 to 1, such as 0.85.'),
        );
    }

    public static function rejected(): TradeSignal
    {
        return TradeSignal::reject(
            new TradeSignalId(self::ID),
            new AnalyticsApplicationId('demo-analytics'),
            self::violations(),
            new SignalOutcomeId(self::OUTCOME_ID),
            new DateTimeImmutable(self::RECEIVED_AT),
        );
    }
}
