<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\TradeSignal;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TradeManeuver\Trading\Domain\TradeSignal\Exception\InvalidTradeSignalValueException;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Confidence;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ContractViolation;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ContractViolations;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ExpectedMove;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Horizon;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Instrument;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Mic;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ReferencePrice;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcomeId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\UtcTimestamp;

final class TradeSignalValueObjectsTest extends TestCase
{
    public function test_identifiers_accept_a_uuid_and_keep_it_in_lower_case(): void
    {
        $this->assertSame('019a5a6e-0000-7000-8000-0000000000a1', (new TradeSignalId('019A5A6E-0000-7000-8000-0000000000A1'))->toString());
        $this->assertSame('019a5a6e-0000-7000-8000-0000000000b1', (new SignalOutcomeId('019a5a6e-0000-7000-8000-0000000000B1'))->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUuids(): iterable
    {
        yield 'empty' => [''];
        yield 'no hyphens' => ['019a5a6e00007000800000000000000a1'];
        yield 'not hexadecimal' => ['019a5a6e-0000-7000-8000-00000000000g'];
        yield 'surrounding text' => [' 019a5a6e-0000-7000-8000-0000000000a1'];
        yield 'trailing newline' => ["019a5a6e-0000-7000-8000-0000000000a1\n"];
    }

    #[DataProvider('invalidUuids')]
    public function test_a_trade_signal_identifier_must_be_a_uuid(string $value): void
    {
        $this->expectExceptionObject(new InvalidTradeSignalValueException('The trade signal identifier must be a UUID.'));

        new TradeSignalId($value);
    }

    #[DataProvider('invalidUuids')]
    public function test_a_signal_outcome_identifier_must_be_a_uuid(string $value): void
    {
        $this->expectExceptionObject(new InvalidTradeSignalValueException('The signal outcome identifier must be a UUID.'));

        new SignalOutcomeId($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function micFormats(): iterable
    {
        yield 'fictional exchange' => ['DEMO'];
        yield 'letters and digits' => ['DEM0'];
    }

    #[DataProvider('micFormats')]
    public function test_an_exchange_code_with_the_mic_format_passes_without_a_registry_lookup(string $code): void
    {
        $this->assertSame($code, (new Mic($code))->code());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidMics(): iterable
    {
        yield 'lower case' => ['demo'];
        yield 'three characters' => ['DM0'];
        yield 'five characters' => ['DEMOX'];
        yield 'punctuation' => ['DE-O'];
        yield 'trailing newline' => ["DEMO\n"];
    }

    #[DataProvider('invalidMics')]
    public function test_an_exchange_code_without_the_mic_format_is_refused(string $code): void
    {
        $this->expectExceptionObject(new InvalidTradeSignalValueException('The exchange code must have the MIC format: four upper-case letters or digits.'));

        new Mic($code);
    }

    public function test_an_instrument_needs_a_symbol(): void
    {
        $instrument = new Instrument('DEMO_A', new Mic('DEMO'));
        $this->assertSame('DEMO_A', $instrument->symbol());
        $this->assertSame('DEMO', $instrument->mic()->code());

        try {
            new Instrument(' ', new Mic('DEMO'));
            $this->fail('An empty symbol must be refused.');
        } catch (InvalidTradeSignalValueException $exception) {
            $this->assertSame('The instrument symbol must not be empty.', $exception->getMessage());
            $this->assertSame('symbol', $exception->part);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function confidences(): iterable
    {
        yield 'zero' => ['0'];
        yield 'one' => ['1'];
        yield 'one with a scale' => ['1.000'];
        yield 'fraction with its scale' => ['0.850'];
    }

    #[DataProvider('confidences')]
    public function test_a_confidence_from_0_to_1_keeps_its_scale(string $value): void
    {
        $this->assertSame($value, Confidence::of($value)->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidConfidences(): iterable
    {
        yield 'above one' => ['1.0001'];
        yield 'negative' => ['-0.01'];
        yield 'exponent' => ['8.5e-1'];
        yield 'not a number' => ['high'];
    }

    #[DataProvider('invalidConfidences')]
    public function test_a_confidence_outside_0_to_1_or_not_a_decimal_is_refused(string $value): void
    {
        $this->expectExceptionObject(new InvalidTradeSignalValueException('The confidence must be a decimal string from 0 to 1, such as 0.85.'));

        Confidence::of($value);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function timestamps(): iterable
    {
        yield 'Z suffix' => ['2026-11-07T09:30:00Z', '2026-11-07T09:30:00.000000Z'];
        yield 'zero offset' => ['2026-11-07T09:30:00+00:00', '2026-11-07T09:30:00.000000Z'];
        yield 'milliseconds' => ['2026-11-07T09:30:00.25Z', '2026-11-07T09:30:00.250000Z'];
        yield 'microseconds' => ['2026-11-07T09:30:00.123456Z', '2026-11-07T09:30:00.123456Z'];
        yield 'leap day' => ['2028-02-29T00:00:00Z', '2028-02-29T00:00:00.000000Z'];
    }

    #[DataProvider('timestamps')]
    public function test_a_utc_timestamp_is_read_with_up_to_microseconds(string $value, string $expected): void
    {
        $timestamp = UtcTimestamp::fromString($value);

        $this->assertSame($expected, $timestamp->toString());
        $this->assertSame('UTC', $timestamp->dateTime()->getTimezone()->getName());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTimestamps(): iterable
    {
        yield 'another offset' => ['2026-11-07T10:30:00+01:00'];
        yield 'no zone' => ['2026-11-07T09:30:00'];
        yield 'date only' => ['2026-11-07'];
        yield 'impossible date' => ['2026-02-30T09:30:00Z'];
        yield 'impossible time' => ['2026-11-07T24:30:00Z'];
        yield 'nanoseconds' => ['2026-11-07T09:30:00.123456789Z'];
        yield 'space separator' => ['2026-11-07 09:30:00Z'];
        yield 'trailing newline' => ["2026-11-07T09:30:00Z\n"];
    }

    #[DataProvider('invalidTimestamps')]
    public function test_a_time_that_is_not_iso_8601_in_utc_is_refused(string $value): void
    {
        $this->expectExceptionObject(new InvalidTradeSignalValueException('The time must be an ISO 8601 date and time in UTC, such as 2026-11-07T09:30:00Z.'));

        UtcTimestamp::fromString($value);
    }

    public function test_a_stored_time_is_converted_to_utc(): void
    {
        $timestamp = UtcTimestamp::fromDateTime(new DateTimeImmutable('2026-11-07T10:30:00.5+01:00'));

        $this->assertSame('2026-11-07T09:30:00.500000Z', $timestamp->toString());
    }

    public function test_a_horizon_has_sessions_a_date_or_both(): void
    {
        $this->assertSame([5, null], [Horizon::of(5, null)->sessions(), Horizon::of(5, null)->at()]);
        $this->assertSame([null, '2026-11-20'], [Horizon::of(null, '2026-11-20')->sessions(), Horizon::of(null, '2026-11-20')->at()]);
        $this->assertSame([1, '2028-02-29'], [Horizon::of(1, '2028-02-29')->sessions(), Horizon::of(1, '2028-02-29')->at()]);
    }

    /**
     * @return iterable<string, array{int|null, string|null, string, string|null}>
     */
    public static function invalidHorizons(): iterable
    {
        yield 'neither' => [null, null, 'The horizon needs a number of trading sessions, a date, or both.', null];
        yield 'zero sessions' => [0, null, 'The horizon sessions must be a positive integer.', 'sessions'];
        yield 'negative sessions' => [-1, '2026-11-20', 'The horizon sessions must be a positive integer.', 'sessions'];
        yield 'date with a time' => [null, '2026-11-20T00:00:00Z', 'The horizon date must be a date such as 2026-11-20.', 'at'];
        yield 'impossible date' => [5, '2026-02-30', 'The horizon date must be a date such as 2026-11-20.', 'at'];
    }

    #[DataProvider('invalidHorizons')]
    public function test_an_invalid_horizon_is_refused_with_its_part(?int $sessions, ?string $at, string $message, ?string $part): void
    {
        try {
            Horizon::of($sessions, $at);
            $this->fail('The horizon must be refused.');
        } catch (InvalidTradeSignalValueException $exception) {
            $this->assertSame($message, $exception->getMessage());
            $this->assertSame($part, $exception->part);
        }
    }

    public function test_an_expected_move_keeps_its_exact_percentages(): void
    {
        $expectedMove = ExpectedMove::of('-1.50', '4.0');
        $this->assertSame(['-1.50', '4.0'], [$expectedMove->min(), $expectedMove->max()]);

        $equal = ExpectedMove::of('2.5', '2.50');
        $this->assertSame(['2.5', '2.50'], [$equal->min(), $equal->max()]);
    }

    /**
     * @return iterable<string, array{string, string, string, string|null}>
     */
    public static function invalidExpectedMoves(): iterable
    {
        yield 'minimum above maximum' => ['4.01', '4.0', 'The expected move minimum must not be greater than its maximum.', null];
        yield 'minimum not a decimal' => ['1e1', '4.0', 'The expected move min must be a decimal percentage string, such as 2.5.', 'min'];
        yield 'maximum not a decimal' => ['1.5', '4%', 'The expected move max must be a decimal percentage string, such as 2.5.', 'max'];
    }

    #[DataProvider('invalidExpectedMoves')]
    public function test_an_invalid_expected_move_is_refused_with_its_part(string $min, string $max, string $message, ?string $part): void
    {
        try {
            ExpectedMove::of($min, $max);
            $this->fail('The expected move must be refused.');
        } catch (InvalidTradeSignalValueException $exception) {
            $this->assertSame($message, $exception->getMessage());
            $this->assertSame($part, $exception->part);
        }
    }

    public function test_a_reference_price_keeps_the_scale_it_was_sent_with(): void
    {
        $referencePrice = ReferencePrice::of('12.3456', 'EUR', '2026-11-07T08:59:00Z');

        $this->assertSame('12.3456', $referencePrice->amount());
        $this->assertSame('EUR', $referencePrice->currencyCode());
        $this->assertSame('2026-11-07T08:59:00.000000Z', $referencePrice->asOf()->toString());
    }

    /**
     * @return iterable<string, array{string, string, string, string, string}>
     */
    public static function invalidReferencePrices(): iterable
    {
        $amount = 'The reference price amount must be a decimal string greater than zero, such as 12.50.';
        $currency = 'The reference price currency must be an ISO 4217 code, such as EUR.';
        yield 'zero amount' => ['0.00', 'EUR', '2026-11-07T08:59:00Z', $amount, 'amount'];
        yield 'negative amount' => ['-12.50', 'EUR', '2026-11-07T08:59:00Z', $amount, 'amount'];
        yield 'amount with an exponent' => ['1.25e1', 'EUR', '2026-11-07T08:59:00Z', $amount, 'amount'];
        yield 'unknown currency' => ['12.50', 'DEMO', '2026-11-07T08:59:00Z', $currency, 'currency'];
        yield 'lower-case currency' => ['12.50', 'eur', '2026-11-07T08:59:00Z', $currency, 'currency'];
        yield 'time in another zone' => ['12.50', 'EUR', '2026-11-07T09:59:00+01:00', 'The time must be an ISO 8601 date and time in UTC, such as 2026-11-07T09:30:00Z.', 'asOf'];
    }

    #[DataProvider('invalidReferencePrices')]
    public function test_an_invalid_reference_price_is_refused_with_its_part(string $amount, string $currency, string $asOf, string $message, string $part): void
    {
        try {
            ReferencePrice::of($amount, $currency, $asOf);
            $this->fail('The reference price must be refused.');
        } catch (InvalidTradeSignalValueException $exception) {
            $this->assertSame($message, $exception->getMessage());
            $this->assertSame($part, $exception->part);
        }
    }

    public function test_contract_violations_are_described_as_field_and_message_in_their_order(): void
    {
        $violations = ContractViolations::of(
            new ContractViolation('confidence', 'The confidence must be from 0 to 1.'),
            new ContractViolation('source', 'The analytics application is not authorized for the investment configuration.'),
        );

        $this->assertSame([
            'confidence: The confidence must be from 0 to 1.',
            'source: The analytics application is not authorized for the investment configuration.',
        ], $violations->descriptions());
    }
}
