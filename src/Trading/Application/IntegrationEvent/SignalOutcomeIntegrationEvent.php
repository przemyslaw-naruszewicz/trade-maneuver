<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\IntegrationEvent;

use DateTimeZone;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ContractViolations;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcome;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

/**
 * The public `tm.signal-outcome.v1` message, documented in docs/integration.md. The recipient is the analytics
 * application that sent the trade signal; it routes the message and is not part of the payload. A field that does not
 * apply to the outcome is absent: only a signal rejection has violations, and only a signal decline a reason.
 */
final readonly class SignalOutcomeIntegrationEvent
{
    public const string SOURCE = 'trade-maneuver';

    public const string TYPE = 'tm.signal-outcome.v1';

    /**
     * @param  list<array{field: string, message: string}>|null  $violations
     */
    private function __construct(
        public string $id,
        public string $time,
        public string $signalId,
        public string $outcome,
        public ?array $violations,
        public ?string $reason,
        public string $recipient,
    ) {}

    public static function of(TradeSignalId $signalId, SignalOutcome $outcome, AnalyticsApplicationId $recipient): self
    {
        return new self(
            $outcome->id->toString(),
            $outcome->occurredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'),
            $signalId->toString(),
            $outcome->type->value,
            $outcome->violations === null ? null : self::violationsOf($outcome->violations),
            $outcome->reason?->value,
            $recipient->toString(),
        );
    }

    /**
     * @return array{id: string, source: string, type: string, time: string, signalId: string, outcome: string, violations?: list<array{field: string, message: string}>, reason?: string}
     */
    public function payload(): array
    {
        $payload = [
            'id' => $this->id,
            'source' => self::SOURCE,
            'type' => self::TYPE,
            'time' => $this->time,
            'signalId' => $this->signalId,
            'outcome' => $this->outcome,
        ];
        if ($this->violations !== null) {
            $payload['violations'] = $this->violations;
        }
        if ($this->reason !== null) {
            $payload['reason'] = $this->reason;
        }

        return $payload;
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    private static function violationsOf(ContractViolations $violations): array
    {
        $fields = [];
        foreach ($violations->all() as $violation) {
            $fields[] = ['field' => $violation->field, 'message' => $violation->message];
        }

        return $fields;
    }
}
