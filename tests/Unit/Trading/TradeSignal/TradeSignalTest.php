<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\TradeSignal;

use Brick\Money\Currency;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Trading\TradeSignal\Support\TradeSignalBuilder;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationCurrency;
use TradeManeuver\Trading\Domain\TradeSignal\Dto\TradeSignalStateDto;
use TradeManeuver\Trading\Domain\TradeSignal\Enum\DeclineReason;
use TradeManeuver\Trading\Domain\TradeSignal\Enum\Direction;
use TradeManeuver\Trading\Domain\TradeSignal\Enum\SignalOutcomeType;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalDeclined;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalRejected;
use TradeManeuver\Trading\Domain\TradeSignal\Exception\InvalidTradeSignalStateException;
use TradeManeuver\Trading\Domain\TradeSignal\Exception\InvalidTradeSignalValueException;
use TradeManeuver\Trading\Domain\TradeSignal\TradeSignal;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ContractViolation;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ContractViolations;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\InstrumentListing;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcomeId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

final class TradeSignalTest extends TestCase
{
    private const string EVALUATED_AT = '2026-11-09T10:00:00.500000Z';

    public function test_a_rejection_ends_the_trade_signal_with_a_signal_rejected_outcome_listing_the_violations(): void
    {
        $tradeSignal = TradeSignalBuilder::rejected();

        $events = $tradeSignal->releaseEvents();

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf(TradeSignalRejected::class, $event);
        $this->assertSame(TradeSignalBuilder::ID, $event->id->toString());
        $this->assertSame('demo-analytics', $event->source->toString());
        $this->assertSame(TradeSignalBuilder::OUTCOME_ID, $event->outcome->id->toString());
        $this->assertEquals(TradeSignalBuilder::violations(), $event->outcome->violations);
        $this->assertEquals(TradeSignalBuilder::violations(), $event->violations);
        $this->assertNull($event->outcome->reason);
        $this->assertSame(TradeSignalBuilder::RECEIVED_AT, $event->outcome->occurredAt->format('Y-m-d\TH:i:s.u\Z'));
        $this->assertSame($event->outcome, $tradeSignal->outcome());
        $this->assertFalse($tradeSignal->awaitsEvaluation());
        $this->assertSame([], $tradeSignal->releaseEvents());
    }

    public function test_a_rejection_needs_at_least_one_violation(): void
    {
        $this->expectException(InvalidTradeSignalValueException::class);
        $this->expectExceptionMessage('A signal rejection needs at least one contract violation.');

        ContractViolations::of();
    }

    public function test_the_violations_form_a_list_in_the_order_given(): void
    {
        $first = new ContractViolation('confidence', 'The confidence must be a decimal string from 0 to 1, such as 0.85.');
        $second = new ContractViolation('direction', 'The field is required.');

        $this->assertSame([$first, $second], ContractViolations::of(...['first' => $first, 'second' => $second])->all());
    }

    public function test_a_received_trade_signal_awaits_evaluation_without_an_outcome(): void
    {
        $tradeSignal = TradeSignalBuilder::received();

        $this->assertTrue($tradeSignal->awaitsEvaluation());
        $this->assertNull($tradeSignal->outcome());
    }

    public function test_reconstitution_keeps_the_stored_state_of_a_received_trade_signal_and_records_no_domain_events(): void
    {
        $received = TradeSignalBuilder::received();

        $reconstituted = TradeSignal::reconstitute($received->id(), $received->state());

        $this->assertSame([], $reconstituted->releaseEvents());
        $this->assertSame(TradeSignalBuilder::ID, $reconstituted->id()->toString());
        $this->assertEquals($received->state(), $reconstituted->state());
        $this->assertTrue($reconstituted->awaitsEvaluation());
    }

    public function test_reconstitution_keeps_the_stored_outcome_of_a_rejected_trade_signal_and_records_no_domain_events(): void
    {
        $rejected = TradeSignalBuilder::rejected();

        $reconstituted = TradeSignal::reconstitute($rejected->id(), $rejected->state());

        $this->assertSame([], $reconstituted->releaseEvents());
        $this->assertEquals($rejected->state(), $reconstituted->state());
        $this->assertEquals($rejected->outcome(), $reconstituted->outcome());
    }

    public function test_reconstitution_refuses_a_stored_trade_signal_without_content_or_outcome(): void
    {
        $this->expectException(InvalidTradeSignalStateException::class);
        $this->expectExceptionMessage('A trade signal without valid content must have a signal outcome.');

        TradeSignal::reconstitute(
            new TradeSignalId(TradeSignalBuilder::ID),
            new TradeSignalStateDto(new AnalyticsApplicationId('demo-analytics'), new DateTimeImmutable(TradeSignalBuilder::RECEIVED_AT), null, null),
        );
    }

    public function test_a_short_trade_signal_is_declined_with_the_reason_unsupported_direction(): void
    {
        $tradeSignal = TradeSignalBuilder::received(Direction::Short);
        $tradeSignal->releaseEvents();

        $tradeSignal->evaluateValidityAndDirection(new SignalOutcomeId(TradeSignalBuilder::OUTCOME_ID), new DateTimeImmutable(self::EVALUATED_AT));

        $this->assertDeclinedWith(DeclineReason::UnsupportedDirection, $tradeSignal);
    }

    /**
     * @return iterable<string, array{Direction, string}>
     */
    public static function expiredTradeSignals(): iterable
    {
        yield 'long, now after validUntil' => [Direction::Long, '2026-11-09T10:00:00.499999Z'];
        yield 'long, now equal to validUntil' => [Direction::Long, self::EVALUATED_AT];
        yield 'short, checked for expiry first' => [Direction::Short, self::EVALUATED_AT];
    }

    #[DataProvider('expiredTradeSignals')]
    public function test_a_trade_signal_whose_validity_has_ended_is_declined_with_the_reason_signal_expired(Direction $direction, string $validUntil): void
    {
        $tradeSignal = TradeSignalBuilder::received($direction, $validUntil);
        $tradeSignal->releaseEvents();

        $tradeSignal->evaluateValidityAndDirection(new SignalOutcomeId(TradeSignalBuilder::OUTCOME_ID), new DateTimeImmutable(self::EVALUATED_AT));

        $this->assertDeclinedWith(DeclineReason::SignalExpired, $tradeSignal);
    }

    public function test_a_long_trade_signal_before_its_validity_ends_passes_and_awaits_the_next_checks(): void
    {
        $tradeSignal = TradeSignalBuilder::received(Direction::Long, '2026-11-09T10:00:00.500001Z');
        $tradeSignal->releaseEvents();

        $tradeSignal->evaluateValidityAndDirection(new SignalOutcomeId(TradeSignalBuilder::OUTCOME_ID), new DateTimeImmutable(self::EVALUATED_AT));

        $this->assertNull($tradeSignal->outcome());
        $this->assertTrue($tradeSignal->awaitsEvaluation());
        $this->assertSame([], $tradeSignal->releaseEvents());
    }

    /**
     * @return iterable<string, array{TradeSignal}>
     */
    public static function decidedTradeSignals(): iterable
    {
        $declined = TradeSignalBuilder::received(Direction::Short);
        $declined->evaluateValidityAndDirection(new SignalOutcomeId(TradeSignalBuilder::OUTCOME_ID), new DateTimeImmutable(self::EVALUATED_AT));

        yield 'declined' => [$declined];
        yield 'rejected' => [TradeSignalBuilder::rejected()];
    }

    #[DataProvider('decidedTradeSignals')]
    public function test_a_trade_signal_with_a_signal_outcome_cannot_be_evaluated_again(TradeSignal $decided): void
    {
        $outcome = $decided->outcome();
        $decided->releaseEvents();

        try {
            $decided->evaluateValidityAndDirection(new SignalOutcomeId('019a5a6e-0000-7000-8000-0000000000b2'), new DateTimeImmutable(self::EVALUATED_AT));
            $this->fail('Evaluating a trade signal with a signal outcome was expected to be refused.');
        } catch (InvalidTradeSignalStateException $exception) {
            $this->assertSame('Only a trade signal that awaits evaluation can be evaluated.', $exception->getMessage());
        }
        $this->assertSame($outcome, $decided->outcome());
        $this->assertSame([], $decided->releaseEvents());
    }

    /**
     * @return iterable<string, array{?InstrumentListing, string, DeclineReason, string}>
     */
    public static function instrumentDeclines(): iterable
    {
        yield 'unknown to the broker' => [null, 'EUR', DeclineReason::InstrumentUnavailable, 'InstrumentUnavailable'];
        yield 'unavailable' => [InstrumentListing::unavailable(Currency::of('EUR')), 'EUR', DeclineReason::InstrumentUnavailable, 'InstrumentUnavailable'];
        yield 'unavailable, checked before the reference price currency' => [InstrumentListing::unavailable(Currency::of('USD')), 'EUR', DeclineReason::InstrumentUnavailable, 'InstrumentUnavailable'];
        yield 'quoted in USD for an investment configuration in EUR' => [InstrumentListing::available(Currency::of('USD')), 'USD', DeclineReason::UnsupportedCurrency, 'UnsupportedCurrency'];
    }

    #[DataProvider('instrumentDeclines')]
    public function test_the_instrument_checks_decline_with_their_reason_code(?InstrumentListing $listing, string $referencePriceCurrency, DeclineReason $reason, string $reasonCode): void
    {
        $tradeSignal = TradeSignalBuilder::received(referencePriceCurrency: $referencePriceCurrency);
        $tradeSignal->releaseEvents();

        $tradeSignal->checkInstrument($listing, InvestmentConfigurationCurrency::fromCode('EUR'), new SignalOutcomeId(TradeSignalBuilder::OUTCOME_ID), new DateTimeImmutable(self::EVALUATED_AT));

        $this->assertSame($reasonCode, $tradeSignal->outcome()?->reason?->value);
        $this->assertDeclinedWith($reason, $tradeSignal);
    }

    public function test_a_reference_price_in_another_currency_than_the_quote_currency_ends_with_signal_rejected(): void
    {
        $tradeSignal = TradeSignalBuilder::received(referencePriceCurrency: 'EUR');
        $tradeSignal->releaseEvents();

        // The quote currency USD also differs from the investment configuration's, which is checked only afterwards.
        $tradeSignal->checkInstrument(InstrumentListing::available(Currency::of('USD')), InvestmentConfigurationCurrency::fromCode('EUR'), new SignalOutcomeId(TradeSignalBuilder::OUTCOME_ID), new DateTimeImmutable(self::EVALUATED_AT));

        $outcome = $tradeSignal->outcome();
        $this->assertNotNull($outcome);
        $this->assertSame(SignalOutcomeType::SignalRejected, $outcome->type);
        $this->assertNull($outcome->reason);
        $this->assertSame(TradeSignalBuilder::OUTCOME_ID, $outcome->id->toString());
        $this->assertSame(self::EVALUATED_AT, $outcome->occurredAt->format('Y-m-d\TH:i:s.u\Z'));
        $expected = ContractViolations::of(new ContractViolation('referencePrice.currency', "The reference price currency must be the instrument's quote currency USD."));
        $this->assertEquals($expected, $outcome->violations);
        $this->assertFalse($tradeSignal->awaitsEvaluation());
        $events = $tradeSignal->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TradeSignalRejected::class, $events[0]);
        $this->assertSame(TradeSignalBuilder::ID, $events[0]->id->toString());
        $this->assertSame('demo-analytics', $events[0]->source->toString());
        $this->assertSame($outcome, $events[0]->outcome);
        $this->assertEquals($expected, $events[0]->violations);
    }

    public function test_an_available_instrument_quoted_in_the_investment_configuration_currency_passes_and_awaits_order_creation(): void
    {
        $tradeSignal = TradeSignalBuilder::received();
        $tradeSignal->releaseEvents();

        $tradeSignal->checkInstrument(InstrumentListing::available(Currency::of('EUR')), InvestmentConfigurationCurrency::fromCode('EUR'), new SignalOutcomeId(TradeSignalBuilder::OUTCOME_ID), new DateTimeImmutable(self::EVALUATED_AT));

        $this->assertNull($tradeSignal->outcome());
        $this->assertTrue($tradeSignal->awaitsEvaluation());
        $this->assertSame([], $tradeSignal->releaseEvents());
    }

    #[DataProvider('decidedTradeSignals')]
    public function test_the_instrument_of_a_trade_signal_with_a_signal_outcome_cannot_be_checked(TradeSignal $decided): void
    {
        $outcome = $decided->outcome();
        $decided->releaseEvents();

        try {
            $decided->checkInstrument(null, InvestmentConfigurationCurrency::fromCode('EUR'), new SignalOutcomeId('019a5a6e-0000-7000-8000-0000000000b2'), new DateTimeImmutable(self::EVALUATED_AT));
            $this->fail('Checking the instrument of a trade signal with a signal outcome was expected to be refused.');
        } catch (InvalidTradeSignalStateException $exception) {
            $this->assertSame('Only a trade signal that awaits evaluation can be evaluated.', $exception->getMessage());
        }
        $this->assertSame($outcome, $decided->outcome());
        $this->assertSame([], $decided->releaseEvents());
    }

    public function test_a_valid_trade_signal_names_its_instrument_and_investment_configuration(): void
    {
        $tradeSignal = TradeSignalBuilder::received();

        $this->assertSame('DEMO_A', $tradeSignal->instrument()->symbol());
        $this->assertSame('DEMO', $tradeSignal->instrument()->mic()->code());
        $this->assertSame('demo-investment', $tradeSignal->investmentConfigurationRef()->toString());
    }

    public function test_a_rejected_trade_signal_without_content_has_no_instrument(): void
    {
        $this->expectException(InvalidTradeSignalStateException::class);
        $this->expectExceptionMessage('A rejected trade signal without valid content has no instrument.');

        TradeSignalBuilder::rejected()->instrument();
    }

    private function assertDeclinedWith(DeclineReason $reason, TradeSignal $tradeSignal): void
    {
        $outcome = $tradeSignal->outcome();
        $this->assertNotNull($outcome, 'The trade signal was expected to be declined.');
        $this->assertSame(SignalOutcomeType::SignalDeclined, $outcome->type);
        $this->assertSame($reason, $outcome->reason);
        $this->assertSame(TradeSignalBuilder::OUTCOME_ID, $outcome->id->toString());
        $this->assertSame(self::EVALUATED_AT, $outcome->occurredAt->format('Y-m-d\TH:i:s.u\Z'));
        $this->assertFalse($tradeSignal->awaitsEvaluation());

        $events = $tradeSignal->releaseEvents();
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf(TradeSignalDeclined::class, $event);
        $this->assertSame(TradeSignalBuilder::ID, $event->id->toString());
        $this->assertSame('demo-analytics', $event->source->toString());
        $this->assertSame($outcome, $event->outcome);
        $this->assertSame($reason, $event->reason);
        $this->assertNull($outcome->violations);
        $this->assertSame([], $tradeSignal->releaseEvents());
    }
}
