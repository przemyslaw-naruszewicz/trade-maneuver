<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\TradeSignal;

use DateTimeImmutable;
use Illuminate\Support\Str;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationRef;
use TradeManeuver\Trading\Domain\TradeSignal\Dto\TradeSignalStateDto;
use TradeManeuver\Trading\Domain\TradeSignal\Enum\Direction;
use TradeManeuver\Trading\Domain\TradeSignal\Repository\Exception\TradeSignalDoesNotExistException;
use TradeManeuver\Trading\Domain\TradeSignal\Repository\TradeSignalRepositoryInterface;
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
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcome;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcomeId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalContent;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\UtcTimestamp;
use TradeManeuver\Trading\Infrastructure\TradeSignal\Eloquent\TradeSignalModel;

/**
 * The primary key on the trade signal id refuses a second trade signal with the same id, also under concurrency.
 */
final class TradeSignalRepository implements TradeSignalRepositoryInterface
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s.uP';

    public function nextOutcomeIdentity(): SignalOutcomeId
    {
        return new SignalOutcomeId(Str::uuid7()->toString());
    }

    public function add(TradeSignal $tradeSignal): void
    {
        $state = $tradeSignal->state();

        $model = new TradeSignalModel;
        $model->id = $tradeSignal->id()->toString();
        $model->source = $state->source->toString();
        $model->received_at = $state->receivedAt->format(self::TIME_FORMAT);
        if ($state->content !== null) {
            $this->fillContent($model, $state->content);
        }
        if ($state->outcome !== null) {
            $this->fillOutcome($model, $state->outcome);
        }

        $model->save();
    }

    public function get(TradeSignalId $id): TradeSignal
    {
        $model = TradeSignalModel::query()->find($id->toString());
        if ($model === null) {
            throw TradeSignalDoesNotExistException::withId($id);
        }

        return TradeSignal::reconstitute(
            new TradeSignalId($model->id),
            new TradeSignalStateDto(
                new AnalyticsApplicationId($model->source),
                new DateTimeImmutable($model->received_at),
                $this->contentOf($model),
                $this->outcomeOf($model),
            ),
        );
    }

    private function fillContent(TradeSignalModel $model, TradeSignalContent $content): void
    {
        $model->issued_at = $content->issuedAt->dateTime()->format(self::TIME_FORMAT);
        $model->investment_configuration_ref = $content->investmentConfigurationRef->toString();
        $model->instrument_symbol = $content->instrument->symbol();
        $model->instrument_mic = $content->instrument->mic()->code();
        $model->direction = $content->direction->value;
        $model->confidence = $content->confidence->toString();
        $model->horizon_sessions = $content->horizon->sessions();
        $model->horizon_at = $content->horizon->at();
        $model->expected_move_min = $content->expectedMove?->min();
        $model->expected_move_max = $content->expectedMove?->max();
        $model->reference_price_amount = $content->referencePrice->amount();
        $model->reference_price_currency = $content->referencePrice->currencyCode();
        $model->reference_price_as_of = $content->referencePrice->asOf()->dateTime()->format(self::TIME_FORMAT);
        $model->valid_until = $content->validUntil->dateTime()->format(self::TIME_FORMAT);
        $model->extensions = $content->extensions?->toJson();
    }

    private function fillOutcome(TradeSignalModel $model, SignalOutcome $outcome): void
    {
        $violations = [];
        foreach ($outcome->violations->all() as $violation) {
            $violations[] = ['field' => $violation->field, 'message' => $violation->message];
        }

        $model->outcome_id = $outcome->id->toString();
        $model->outcome = $outcome->type->value;
        $model->outcome_occurred_at = $outcome->occurredAt->format(self::TIME_FORMAT);
        $model->outcome_violations = $violations;
    }

    private function contentOf(TradeSignalModel $model): ?TradeSignalContent
    {
        if ($model->issued_at === null) {
            return null;
        }

        return new TradeSignalContent(
            self::timestampOf($model->issued_at),
            new InvestmentConfigurationRef((string) $model->investment_configuration_ref),
            new Instrument((string) $model->instrument_symbol, new Mic((string) $model->instrument_mic)),
            Direction::from((string) $model->direction),
            Confidence::of((string) $model->confidence),
            Horizon::of($model->horizon_sessions, $model->horizon_at),
            $model->expected_move_min === null ? null : ExpectedMove::of($model->expected_move_min, (string) $model->expected_move_max),
            ReferencePrice::of(
                (string) $model->reference_price_amount,
                (string) $model->reference_price_currency,
                self::timestampOf((string) $model->reference_price_as_of)->toString(),
            ),
            self::timestampOf((string) $model->valid_until),
            $model->extensions === null ? null : self::extensionsOf($model->extensions),
        );
    }

    private function outcomeOf(TradeSignalModel $model): ?SignalOutcome
    {
        if ($model->outcome_id === null) {
            return null;
        }

        $violations = [];
        foreach ($model->outcome_violations ?? [] as $violation) {
            $violations[] = new ContractViolation($violation['field'], $violation['message']);
        }

        return SignalOutcome::rejection(
            new SignalOutcomeId($model->outcome_id),
            ContractViolations::of(...$violations),
            new DateTimeImmutable((string) $model->outcome_occurred_at),
        );
    }

    private static function extensionsOf(string $json): Extensions
    {
        /** @var array<string, mixed> $values */
        $values = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return Extensions::of($values);
    }

    private static function timestampOf(string $value): UtcTimestamp
    {
        return UtcTimestamp::fromDateTime(new DateTimeImmutable($value));
    }
}
