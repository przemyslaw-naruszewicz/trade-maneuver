<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal;

use DateTimeImmutable;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\TradeSignal\Dto\TradeSignalStateDto;
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
    /** @var list<TradeSignalReceived|TradeSignalRejected> */
    private array $recordedEvents = [];

    /**
     * @throws InvalidTradeSignalStateException
     */
    private function __construct(
        private readonly TradeSignalId $id,
        private readonly AnalyticsApplicationId $source,
        private readonly DateTimeImmutable $receivedAt,
        private readonly ?TradeSignalContent $content,
        private readonly ?SignalOutcome $outcome,
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

        return new self($id, $source, $rejectedAt, null, $outcome);
    }

    /**
     * Rebuilds a stored trade signal without recording domain events.
     *
     * @throws InvalidTradeSignalStateException
     */
    public static function reconstitute(TradeSignalId $id, TradeSignalStateDto $state): self
    {
        if ($state->content !== null) {
            return self::receive($id, $state->source, $state->content, $state->receivedAt);
        }

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

    /**
     * @return list<TradeSignalReceived|TradeSignalRejected>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
