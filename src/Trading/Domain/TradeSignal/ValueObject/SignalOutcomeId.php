<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\ValueObject;

use TradeManeuver\Trading\Domain\TradeSignal\Exception\InvalidTradeSignalValueException;

/**
 * A UUID in canonical form; upper-case hexadecimal digits are accepted and kept in lower case.
 */
final readonly class SignalOutcomeId
{
    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di';

    private string $value;

    /**
     * @throws InvalidTradeSignalValueException
     */
    public function __construct(string $value)
    {
        if (preg_match(self::UUID_PATTERN, $value) !== 1) {
            throw new InvalidTradeSignalValueException('The signal outcome identifier must be a UUID.');
        }
        $this->value = strtolower($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
