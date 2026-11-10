<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Workflow\TradeSignalEvaluation;

use DateTimeImmutable;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

/**
 * The evaluation steps a trade signal has passed. A step that is passed is never run again.
 */
final class TradeSignalEvaluationProgress
{
    /** @var array<string, DateTimeImmutable> the time each step was passed, by step */
    private array $newlyPassed = [];

    /**
     * @param  list<EvaluationStep>  $passedSteps
     */
    private function __construct(private readonly TradeSignalId $tradeSignalId, private array $passedSteps) {}

    /**
     * @param  list<EvaluationStep>  $passedSteps
     */
    public static function of(TradeSignalId $tradeSignalId, array $passedSteps): self
    {
        return new self($tradeSignalId, $passedSteps);
    }

    public function tradeSignalId(): TradeSignalId
    {
        return $this->tradeSignalId;
    }

    public function hasPassed(EvaluationStep $step): bool
    {
        return in_array($step, $this->passedSteps, true);
    }

    public function pass(EvaluationStep $step, DateTimeImmutable $passedAt): void
    {
        if ($this->hasPassed($step)) {
            return;
        }

        $this->passedSteps[] = $step;
        $this->newlyPassed[$step->value] = $passedAt;
    }

    /**
     * The steps passed since the progress was loaded, which the store has yet to write.
     *
     * @return array<string, DateTimeImmutable>
     */
    public function newlyPassed(): array
    {
        return $this->newlyPassed;
    }
}
