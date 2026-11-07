<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal;

use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationRef;
use TradeManeuver\Trading\Domain\TradeSignal\Enum\Direction;
use TradeManeuver\Trading\Domain\TradeSignal\Exception\InvalidTradeSignalValueException;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Confidence;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ContractViolations;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ExpectedMove;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Horizon;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Instrument;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Mic;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ReferencePrice;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalContent;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\UtcTimestamp;

/**
 * Translates a decoded `tm.trade-signal.v1` message into trade signal content, or into the list of its contract
 * violations. The domain value objects decide which values are valid; this class locates and reports them.
 * The envelope fields `id` and `source` are checked before translation, and unknown fields are ignored.
 */
final class TradeSignalContentTranslator
{
    public const string TYPE = 'tm.trade-signal.v1';

    /**
     * @param  array<mixed>  $message
     */
    public function translate(array $message): TradeSignalContent|ContractViolations
    {
        $fields = new MessageFields($message);

        $this->checkType($fields);
        $issuedAt = $fields->required('time', UtcTimestamp::fromString(...));
        $investmentConfigurationRef = $fields->required(
            'investmentConfigurationRef',
            static fn (string $reference): InvestmentConfigurationRef => new InvestmentConfigurationRef($reference),
        );
        $instrument = $this->instrument($fields);
        $direction = $fields->required('direction', self::direction(...));
        $confidence = $fields->required('confidence', Confidence::of(...));
        $horizon = $this->horizon($fields);
        $expectedMove = $this->expectedMove($fields);
        $referencePrice = $this->referencePrice($fields);
        $validUntil = $fields->required('validUntil', UtcTimestamp::fromString(...));
        $fields->optionalObject('extensions');

        return $this->content(
            $fields,
            $issuedAt,
            $investmentConfigurationRef,
            $instrument,
            $direction,
            $confidence,
            $horizon,
            $expectedMove,
            $referencePrice,
            $validUntil,
        );
    }

    /**
     * A required value is null only together with its violation, so the null checks exist for the types alone and
     * their mutants are equivalent.
     *
     * @infection-ignore-all
     */
    private function content(
        MessageFields $fields,
        ?UtcTimestamp $issuedAt,
        ?InvestmentConfigurationRef $investmentConfigurationRef,
        ?Instrument $instrument,
        ?Direction $direction,
        ?Confidence $confidence,
        ?Horizon $horizon,
        ?ExpectedMove $expectedMove,
        ?ReferencePrice $referencePrice,
        ?UtcTimestamp $validUntil,
    ): TradeSignalContent|ContractViolations {
        if ($fields->violations() !== [] || $issuedAt === null || $investmentConfigurationRef === null
            || $instrument === null || $direction === null || $confidence === null || $horizon === null
            || $referencePrice === null || $validUntil === null) {
            return ContractViolations::of(...$fields->violations());
        }

        return new TradeSignalContent(
            $issuedAt,
            $investmentConfigurationRef,
            $instrument,
            $direction,
            $confidence,
            $horizon,
            $expectedMove,
            $referencePrice,
            $validUntil,
        );
    }

    private function checkType(MessageFields $fields): void
    {
        $type = $fields->string('type');
        if ($type !== null && $type !== self::TYPE) {
            $fields->violate('type', sprintf('The type must be %s.', self::TYPE));
        }
    }

    private static function direction(string $value): Direction
    {
        return Direction::tryFrom($value) ?? throw new InvalidTradeSignalValueException('The direction must be long or short.');
    }

    private function instrument(MessageFields $fields): ?Instrument
    {
        if (! $fields->requiredObject('instrument')) {
            return null;
        }
        $symbol = $fields->string('instrument.symbol');
        $mic = $fields->required('instrument.mic', static fn (string $code): Mic => new Mic($code));
        if ($symbol === null || $mic === null) {
            return null;
        }

        return $fields->attempt('instrument', static fn (): Instrument => new Instrument($symbol, $mic));
    }

    private function horizon(MessageFields $fields): ?Horizon
    {
        if (! $fields->requiredObject('horizon')) {
            return null;
        }
        $sessionsGiven = $fields->has('horizon.sessions');
        $sessions = $fields->optionalInteger('horizon.sessions');
        $at = $fields->optionalString('horizon.at');
        if ($sessionsGiven && $sessions === null) {
            return null;
        }

        return $fields->attempt('horizon', static fn (): Horizon => Horizon::of($sessions, $at));
    }

    private function expectedMove(MessageFields $fields): ?ExpectedMove
    {
        if ($fields->optionalObject('expectedMove') === null) {
            return null;
        }
        $min = $fields->string('expectedMove.min');
        $max = $fields->string('expectedMove.max');
        if ($min === null || $max === null) {
            return null;
        }

        return $fields->attempt('expectedMove', static fn (): ExpectedMove => ExpectedMove::of($min, $max));
    }

    private function referencePrice(MessageFields $fields): ?ReferencePrice
    {
        if (! $fields->requiredObject('referencePrice')) {
            return null;
        }
        $amount = $fields->string('referencePrice.amount');
        $currency = $fields->string('referencePrice.currency');
        $asOf = $fields->string('referencePrice.asOf');
        if ($amount === null || $currency === null || $asOf === null) {
            return null;
        }

        return $fields->attempt('referencePrice', static fn (): ReferencePrice => ReferencePrice::of($amount, $currency, $asOf));
    }
}
