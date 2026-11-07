<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\ValueObject;

use TradeManeuver\Trading\Domain\TradeSignal\Exception\InvalidTradeSignalValueException;

/**
 * An exchange code in the ISO 10383 MIC format. Only the format is checked, so the fictional exchange `DEMO` passes.
 */
final readonly class Mic
{
    private const string PATTERN = '/^[A-Z0-9]{4}$/D';

    /**
     * @throws InvalidTradeSignalValueException
     */
    public function __construct(private string $code)
    {
        if (preg_match(self::PATTERN, $code) !== 1) {
            throw new InvalidTradeSignalValueException('The exchange code must have the MIC format: four upper-case letters or digits.');
        }
    }

    public function code(): string
    {
        return $this->code;
    }
}
