<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\TradeSignal;

use DateTimeImmutable;
use Error;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Tests\Unit\Trading\TradeSignal\Support\TradeSignalBuilder;
use TradeManeuver\Trading\Domain\TradeSignal\Dto\TradeSignalStateDto;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalReceived;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalRejected;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Confidence;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ContractViolation;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ContractViolations;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ExpectedMove;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Horizon;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Instrument;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Mic;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ReferencePrice;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcome;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcomeId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalContent;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\UtcTimestamp;

final class TradeSignalStateTest extends TestCase
{
    public function test_the_state_is_a_detached_copy(): void
    {
        $tradeSignal = TradeSignalBuilder::received();

        $this->assertNotSame($tradeSignal->state(), $tradeSignal->state());
    }

    public function test_a_property_of_the_state_cannot_be_changed(): void
    {
        $tradeSignal = TradeSignalBuilder::received();
        $state = $tradeSignal->state();

        try {
            (new ReflectionProperty($state, 'receivedAt'))->setValue($state, new DateTimeImmutable('2030-01-01T00:00:00Z'));
            $this->fail('The state DTO must not accept a new value.');
        } catch (Error $error) {
            $this->assertStringContainsString('Cannot modify readonly property', $error->getMessage());
        }

        $this->assertSame(TradeSignalBuilder::RECEIVED_AT, $tradeSignal->state()->receivedAt->format('Y-m-d\TH:i:s.u\Z'));
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function immutableTypes(): iterable
    {
        yield 'state DTO' => [TradeSignalStateDto::class];
        yield 'received event' => [TradeSignalReceived::class];
        yield 'rejected event' => [TradeSignalRejected::class];
        yield 'identity' => [TradeSignalId::class];
        yield 'content' => [TradeSignalContent::class];
        yield 'timestamp' => [UtcTimestamp::class];
        yield 'instrument' => [Instrument::class];
        yield 'MIC' => [Mic::class];
        yield 'confidence' => [Confidence::class];
        yield 'horizon' => [Horizon::class];
        yield 'expected move' => [ExpectedMove::class];
        yield 'reference price' => [ReferencePrice::class];
        yield 'signal outcome' => [SignalOutcome::class];
        yield 'signal outcome identity' => [SignalOutcomeId::class];
        yield 'contract violation' => [ContractViolation::class];
        yield 'contract violations' => [ContractViolations::class];
    }

    /**
     * The values these types hold are themselves immutable: DateTimeImmutable, BigDecimal, Currency, enums, scalars and
     * arrays of immutable values.
     *
     * @param  class-string  $type
     */
    #[DataProvider('immutableTypes')]
    public function test_the_state_dto_and_its_values_are_readonly(string $type): void
    {
        $this->assertTrue((new ReflectionClass($type))->isReadOnly(), $type.' must be a readonly class.');
    }
}
