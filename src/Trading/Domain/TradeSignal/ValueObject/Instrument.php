<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\ValueObject;

use TradeManeuver\Trading\Domain\TradeSignal\Exception\InvalidTradeSignalValueException;

/**
 * A security identified by its symbol together with its exchange's MIC code.
 */
final readonly class Instrument
{
    /**
     * @throws InvalidTradeSignalValueException
     */
    public function __construct(private string $symbol, private Mic $mic)
    {
        if (trim($symbol) === '') {
            throw new InvalidTradeSignalValueException('The instrument symbol must not be empty.', 'symbol');
        }
    }

    public function symbol(): string
    {
        return $this->symbol;
    }

    public function mic(): Mic
    {
        return $this->mic;
    }
}
