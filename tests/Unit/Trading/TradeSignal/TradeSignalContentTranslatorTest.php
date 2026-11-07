<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\TradeSignal;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Trading\TradeSignalMessageBuilder;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\TradeSignalContentTranslator;
use TradeManeuver\Trading\Domain\TradeSignal\Enum\Direction;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ContractViolations;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalContent;

final class TradeSignalContentTranslatorTest extends TestCase
{
    private const string ID = '019a5a6e-0000-7000-8000-0000000000a1';

    public function test_a_valid_message_becomes_trade_signal_content_as_sent(): void
    {
        $content = $this->translate(TradeSignalMessageBuilder::aTradeSignal(self::ID)->with('horizon.at', '2026-11-20'));

        $this->assertInstanceOf(TradeSignalContent::class, $content);
        $this->assertSame('2026-11-07T09:00:00.000000Z', $content->issuedAt->toString());
        $this->assertSame('demo-investment', $content->investmentConfigurationRef->toString());
        $this->assertSame('DEMO_A', $content->instrument->symbol());
        $this->assertSame('DEMO', $content->instrument->mic()->code());
        $this->assertSame(Direction::Long, $content->direction);
        $this->assertSame('0.85', $content->confidence->toString());
        $this->assertSame(5, $content->horizon->sessions());
        $this->assertSame('2026-11-20', $content->horizon->at());
        $this->assertSame(['1.5', '4.0'], [$content->expectedMove?->min(), $content->expectedMove?->max()]);
        $this->assertSame('12.50', $content->referencePrice->amount());
        $this->assertSame('EUR', $content->referencePrice->currencyCode());
        $this->assertSame('2026-11-07T08:59:00.000000Z', $content->referencePrice->asOf()->toString());
        $this->assertSame('2026-11-10T16:00:00.000000Z', $content->validUntil->toString());
    }

    public function test_the_optional_fields_may_be_missing(): void
    {
        $content = $this->translate(TradeSignalMessageBuilder::aTradeSignal(self::ID)
            ->without('expectedMove')
            ->without('extensions')
            ->without('horizon.sessions')
            ->with('horizon.at', '2026-11-20')
            ->with('direction', 'short'));

        $this->assertInstanceOf(TradeSignalContent::class, $content);
        $this->assertNull($content->expectedMove);
        $this->assertNull($content->horizon->sessions());
        $this->assertSame(Direction::Short, $content->direction);
    }

    /**
     * @return iterable<string, array{callable(TradeSignalMessageBuilder): TradeSignalMessageBuilder, list<array{string, string}>}>
     */
    public static function violations(): iterable
    {
        $required = 'The field is required.';
        $string = 'The field must be a string.';
        $object = 'The field must be an object.';
        $time = 'The time must be an ISO 8601 date and time in UTC, such as 2026-11-07T09:30:00Z.';

        yield 'another type' => [static fn ($m) => $m->with('type', 'tm.trade-signal.v2'), [['type', 'The type must be tm.trade-signal.v1.']]];
        yield 'missing type' => [static fn ($m) => $m->without('type'), [['type', $required]]];
        yield 'type not a string' => [static fn ($m) => $m->with('type', 1), [['type', $string]]];
        yield 'missing time' => [static fn ($m) => $m->without('time'), [['time', $required]]];
        yield 'time not in UTC' => [static fn ($m) => $m->with('time', '2026-11-07T10:00:00+01:00'), [['time', $time]]];
        yield 'null time' => [static fn ($m) => $m->with('time', null), [['time', $required]]];
        yield 'empty investment configuration reference' => [static fn ($m) => $m->with('investmentConfigurationRef', ' '), [['investmentConfigurationRef', 'The investment configuration reference must not be empty.']]];
        yield 'missing instrument' => [static fn ($m) => $m->without('instrument'), [['instrument', $required]]];
        yield 'instrument not an object' => [static fn ($m) => $m->with('instrument', 'DEMO_A'), [['instrument', $object]]];
        yield 'instrument as a list' => [static fn ($m) => $m->with('instrument', ['DEMO_A', 'DEMO']), [['instrument', $object]]];
        yield 'missing symbol' => [static fn ($m) => $m->without('instrument.symbol'), [['instrument.symbol', $required]]];
        yield 'empty symbol' => [static fn ($m) => $m->with('instrument.symbol', ''), [['instrument.symbol', 'The instrument symbol must not be empty.']]];
        yield 'missing symbol and invalid MIC' => [static fn ($m) => $m->without('instrument.symbol')->with('instrument.mic', 'demo'), [
            ['instrument.symbol', $required],
            ['instrument.mic', 'The exchange code must have the MIC format: four upper-case letters or digits.'],
        ]];
        yield 'invalid direction' => [static fn ($m) => $m->with('direction', 'up'), [['direction', 'The direction must be long or short.']]];
        yield 'confidence as a number' => [static fn ($m) => $m->with('confidence', 0.85), [['confidence', $string]]];
        yield 'confidence above one' => [static fn ($m) => $m->with('confidence', '1.5'), [['confidence', 'The confidence must be a decimal string from 0 to 1, such as 0.85.']]];
        yield 'missing horizon' => [static fn ($m) => $m->without('horizon'), [['horizon', $required]]];
        yield 'empty horizon' => [static fn ($m) => $m->with('horizon', []), [['horizon', 'The horizon needs a number of trading sessions, a date, or both.']]];
        yield 'sessions as a string' => [static fn ($m) => $m->with('horizon.sessions', '5'), [['horizon.sessions', 'The field must be an integer.']]];
        yield 'zero sessions' => [static fn ($m) => $m->with('horizon.sessions', 0), [['horizon.sessions', 'The horizon sessions must be a positive integer.']]];
        yield 'horizon date not a string' => [static fn ($m) => $m->with('horizon.at', 20261120), [['horizon.at', $string]]];
        yield 'invalid horizon date' => [static fn ($m) => $m->with('horizon.at', '20.11.2026'), [['horizon.at', 'The horizon date must be a date such as 2026-11-20.']]];
        yield 'expected move not an object' => [static fn ($m) => $m->with('expectedMove', '1.5-4.0'), [['expectedMove', $object]]];
        yield 'expected move without a maximum' => [static fn ($m) => $m->without('expectedMove.max'), [['expectedMove.max', $required]]];
        yield 'expected move without a minimum' => [static fn ($m) => $m->without('expectedMove.min'), [['expectedMove.min', $required]]];
        yield 'expected move minimum above maximum' => [static fn ($m) => $m->with('expectedMove.min', '5'), [['expectedMove', 'The expected move minimum must not be greater than its maximum.']]];
        yield 'missing reference price' => [static fn ($m) => $m->without('referencePrice'), [['referencePrice', $required]]];
        yield 'missing reference price amount' => [static fn ($m) => $m->without('referencePrice.amount'), [['referencePrice.amount', $required]]];
        yield 'missing reference price currency' => [static fn ($m) => $m->without('referencePrice.currency'), [['referencePrice.currency', $required]]];
        yield 'missing reference price time' => [static fn ($m) => $m->without('referencePrice.asOf'), [['referencePrice.asOf', $required]]];
        yield 'zero reference price' => [static fn ($m) => $m->with('referencePrice.amount', '0'), [['referencePrice.amount', 'The reference price amount must be a decimal string greater than zero, such as 12.50.']]];
        yield 'unknown currency' => [static fn ($m) => $m->with('referencePrice.currency', 'XYZ'), [['referencePrice.currency', 'The reference price currency must be an ISO 4217 code, such as EUR.']]];
        yield 'reference price time not in UTC' => [static fn ($m) => $m->with('referencePrice.asOf', '2026-11-07'), [['referencePrice.asOf', $time]]];
        yield 'missing signal validity' => [static fn ($m) => $m->without('validUntil'), [['validUntil', $required]]];
        yield 'invalid signal validity' => [static fn ($m) => $m->with('validUntil', 'tomorrow'), [['validUntil', $time]]];
        yield 'extensions not an object' => [static fn ($m) => $m->with('extensions', 'amber'), [['extensions', $object]]];
        yield 'several violations' => [static fn ($m) => $m->without('direction')->with('confidence', '2'), [
            ['direction', $required],
            ['confidence', 'The confidence must be a decimal string from 0 to 1, such as 0.85.'],
        ]];
    }

    /**
     * @param  callable(TradeSignalMessageBuilder): TradeSignalMessageBuilder  $change
     * @param  list<array{string, string}>  $expected
     */
    #[DataProvider('violations')]
    public function test_a_contract_violation_is_reported_with_its_field_path(callable $change, array $expected): void
    {
        $result = $this->translate($change(TradeSignalMessageBuilder::aTradeSignal(self::ID)));

        $this->assertInstanceOf(ContractViolations::class, $result);
        $violations = [];
        foreach ($result->all() as $violation) {
            $violations[] = [$violation->field, $violation->message];
        }
        $this->assertSame($expected, $violations);
    }

    private function translate(TradeSignalMessageBuilder $message): TradeSignalContent|ContractViolations
    {
        $fields = json_decode($message->body(), true, 64, JSON_THROW_ON_ERROR);
        $this->assertIsArray($fields);

        return (new TradeSignalContentTranslator)->translate($fields);
    }
}
