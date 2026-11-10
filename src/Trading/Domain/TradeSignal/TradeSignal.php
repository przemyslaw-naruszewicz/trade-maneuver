<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal;

use DateTimeImmutable;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\TradeSignal\Dto\TradeSignalStateDto;
use TradeManeuver\Trading\Domain\TradeSignal\Enum\DeclineReason;
use TradeManeuver\Trading\Domain\TradeSignal\Enum\Direction;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalDeclined;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalReceived;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalRejected;
use TradeManeuver\Trading\Domain\TradeSignal\Exception\InvalidTradeSignalStateException;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ContractViolations;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcome;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcomeId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalContent;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

final class TradeSignal
{
    /** @var list<TradeSignalReceived|TradeSignalRejected|TradeSignalDeclined> */
    private array $recordedEvents = [];

    /**
     * @throws InvalidTradeSignalStateException
     */
    private function __construct(
        private readonly TradeSignalId $id,
        private readonly AnalyticsApplicationId $source,
        private readonly DateTimeImmutable $receivedAt,
        private readonly ?TradeSignalContent $content,
        private ?SignalOutcome $outcome,
    ) {
        if ($content === null && $outcome === null) {
            throw new InvalidTradeSignalStateException('A trade signal without valid content must have a signal outcome.');
        }
    }

    /**
     * Stores a valid trade signal, which then awaits evaluation.
     */
    public static function receive(
        TradeSignalId $id,
        AnalyticsApplicationId $source,
        TradeSignalContent $content,
        DateTimeImmutable $receivedAt,
    ): self {
        $tradeSignal = new self($id, $source, $receivedAt, $content, null);
        $tradeSignal->recordedEvents[] = new TradeSignalReceived($id, $source, $content, $receivedAt);

        return $tradeSignal;
    }

    /**
     * Ends a trade signal whose message violates the contract with the signal outcome `SignalRejected`.
     */
    public static function reject(
        TradeSignalId $id,
        AnalyticsApplicationId $source,
        ContractViolations $violations,
        SignalOutcomeId $outcomeId,
        DateTimeImmutable $rejectedAt,
    ): self {
        $outcome = SignalOutcome::rejection($outcomeId, $violations, $rejectedAt);
        $tradeSignal = new self($id, $source, $rejectedAt, null, $outcome);
        $tradeSignal->recordedEvents[] = new TradeSignalRejected($id, $source, $outcome, $violations);

        return $tradeSignal;
    }

    /**
     * Rebuilds a stored trade signal without recording domain events.
     *
     * @throws InvalidTradeSignalStateException
     */
    public static function reconstitute(TradeSignalId $id, TradeSignalStateDto $state): self
    {
        return new self($id, $state->source, $state->receivedAt, $state->content, $state->outcome);
    }

    public function id(): TradeSignalId
    {
        return $this->id;
    }

    public function state(): TradeSignalStateDto
    {
        return new TradeSignalStateDto($this->source, $this->receivedAt, $this->content, $this->outcome);
    }

    /**
     * The evaluation checks that need no broker, in this order: a trade signal whose validity has ended by the time of
     * evaluation ends with `SignalDeclined` and the reason `SignalExpired`, and a short one with the reason
     * `UnsupportedDirection`. A trade signal that passes both awaits the next checks.
     *
     * @throws InvalidTradeSignalStateException when the trade signal already has a signal outcome
     */
    public function evaluateValidityAndDirection(SignalOutcomeId $outcomeId, DateTimeImmutable $evaluatedAt): void
    {
        $content = $this->content;
        if ($content === null || $this->outcome !== null) {
            throw new InvalidTradeSignalStateException('Only a trade signal that awaits evaluation can be evaluated.');
        }

        if ($evaluatedAt >= $content->validUntil->dateTime()) {
            $this->decline(DeclineReason::SignalExpired, $outcomeId, $evaluatedAt);
        } elseif ($content->direction === Direction::Short) {
            $this->decline(DeclineReason::UnsupportedDirection, $outcomeId, $evaluatedAt);
        }
    }

    public function awaitsEvaluation(): bool
    {
        return $this->outcome === null;
    }

    /**
     * The stored signal outcome, which a duplicate delivery receives again.
     */
    public function outcome(): ?SignalOutcome
    {
        return $this->outcome;
    }

    private function decline(DeclineReason $reason, SignalOutcomeId $outcomeId, DateTimeImmutable $declinedAt): void
    {
        $this->outcome = SignalOutcome::decline($outcomeId, $reason, $declinedAt);
        $this->recordedEvents[] = new TradeSignalDeclined($this->id, $this->source, $this->outcome, $reason);
    }

    /**
     * Each transition records one event, so a mutant that keeps just the first event is equivalent.
     *
     * @infection-ignore-all
     *
     * @return list<TradeSignalReceived|TradeSignalRejected|TradeSignalDeclined>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
