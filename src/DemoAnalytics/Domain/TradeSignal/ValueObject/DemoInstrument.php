<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject;

use TradeManeuver\DemoAnalytics\Domain\TradeSignal\Exception\InvalidTradeSignalException;

/**
 * A fictional instrument: a symbol starting with DEMO_, always on the fictional exchange DEMO.
 */
final readonly class DemoInstrument
{
    public const string EXCHANGE = 'DEMO';

    private const string SYMBOL_PATTERN = '/^DEMO_[A-Z0-9_]+$/D';

    /**
     * @throws InvalidTradeSignalException
     */
    public function __construct(private string $symbol)
    {
        if (preg_match(self::SYMBOL_PATTERN, $symbol) !== 1) {
            throw new InvalidTradeSignalException('The symbol must start with DEMO_ followed by upper-case letters, digits or underscores, such as DEMO_A.');
        }
    }

    public function symbol(): string
    {
        return $this->symbol;
    }
}
