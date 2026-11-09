<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Application\SignalOutcome\Dto;

/**
 * One `tm.signal-outcome.v1` message as the demo analytics application reads it. A detail that does not apply to the
 * outcome is null or empty.
 */
final readonly class SignalOutcomeDto
{
    /**
     * @param  list<array{field: string, message: string}>  $violations
     */
    public function __construct(
        public string $signalId,
        public string $outcome,
        public ?string $reason,
        public ?int $executedQuantity,
        public array $violations,
    ) {}
}
