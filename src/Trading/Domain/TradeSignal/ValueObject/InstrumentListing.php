<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\ValueObject;

use Brick\Money\Currency;

/**
 * What the broker reports about an instrument it knows: whether it is available and its quote currency.
 */
final readonly class InstrumentListing
{
    private function __construct(private bool $available, private Currency $quoteCurrency) {}

    public static function available(Currency $quoteCurrency): self
    {
        return new self(true, $quoteCurrency);
    }

    public static function unavailable(Currency $quoteCurrency): self
    {
        return new self(false, $quoteCurrency);
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function quoteCurrency(): Currency
    {
        return $this->quoteCurrency;
    }
}
