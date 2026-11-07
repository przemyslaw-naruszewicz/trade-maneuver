<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\ValueObject;

use TradeManeuver\Trading\Domain\TradeSignal\Exception\InvalidTradeSignalValueException;

/**
 * The reasons for a signal rejection; a rejection always has at least one.
 */
final readonly class ContractViolations
{
    /**
     * @param  non-empty-list<ContractViolation>  $violations
     */
    private function __construct(private array $violations) {}

    /**
     * @throws InvalidTradeSignalValueException
     */
    public static function of(ContractViolation ...$violations): self
    {
        if ($violations === []) {
            throw new InvalidTradeSignalValueException('A signal rejection needs at least one contract violation.');
        }

        return new self(array_values($violations));
    }

    /**
     * @return non-empty-list<ContractViolation>
     */
    public function all(): array
    {
        return $this->violations;
    }
}
