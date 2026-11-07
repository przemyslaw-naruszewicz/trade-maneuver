<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\TradeSignal;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Trading\TradeSignal\Support\TradeSignalBuilder;
use TradeManeuver\Trading\Application\IntegrationEvent\SignalOutcomeIntegrationEvent;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcome;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcomeId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

final class SignalOutcomeIntegrationEventTest extends TestCase
{
    public function test_a_signal_rejection_becomes_the_outcome_message_schema(): void
    {
        $outcome = SignalOutcome::rejection(
            new SignalOutcomeId(TradeSignalBuilder::OUTCOME_ID),
            TradeSignalBuilder::violations(),
            new DateTimeImmutable('2026-11-07T10:30:00.25+01:00'),
        );

        $event = SignalOutcomeIntegrationEvent::of(new TradeSignalId(TradeSignalBuilder::ID), $outcome, new AnalyticsApplicationId('demo-analytics'));

        $this->assertSame('demo-analytics', $event->recipient);
        $this->assertSame([
            'id' => TradeSignalBuilder::OUTCOME_ID,
            'source' => 'trade-maneuver',
            'type' => 'tm.signal-outcome.v1',
            'time' => '2026-11-07T09:30:00.250000Z',
            'signalId' => TradeSignalBuilder::ID,
            'outcome' => 'SignalRejected',
            'violations' => [
                ['field' => 'instrument.mic', 'message' => 'The exchange code must have the MIC format: four upper-case letters or digits.'],
                ['field' => 'confidence', 'message' => 'The confidence must be a decimal string from 0 to 1, such as 0.85.'],
            ],
        ], $event->payload());
    }
}
