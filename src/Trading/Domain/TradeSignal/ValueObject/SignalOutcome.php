<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\ValueObject;

use DateTimeImmutable;
use TradeManeuver\Trading\Domain\TradeSignal\Enum\DeclineReason;
use TradeManeuver\Trading\Domain\TradeSignal\Enum\SignalOutcomeType;

/**
 * The terminal result of processing a trade signal. Its identity and time stay the same whenever it is reported again.
 * A signal rejection lists its contract violations, and a signal decline gives its reason.
 */
final readonly class SignalOutcome
{
    private function __construct(
        public SignalOutcomeId $id,
        public SignalOutcomeType $type,
        public DateTimeImmutable $occurredAt,
        public ?ContractViolations $violations,
        public ?DeclineReason $reason,
    ) {}

    public static function rejection(SignalOutcomeId $id, ContractViolations $violations, DateTimeImmutable $occurredAt): self
    {
        return new self($id, SignalOutcomeType::SignalRejected, $occurredAt, $violations, null);
    }

    public static function decline(SignalOutcomeId $id, DeclineReason $reason, DateTimeImmutable $occurredAt): self
    {
        return new self($id, SignalOutcomeType::SignalDeclined, $occurredAt, null, $reason);
    }
}
