<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\ValueObject;

/**
 * One breach of the trade signal contract: the path of the field, such as `instrument.mic`, and what is wrong with it.
 */
final readonly class ContractViolation
{
    public function __construct(public string $field, public string $message) {}
}
