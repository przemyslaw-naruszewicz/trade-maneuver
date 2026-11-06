<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject;

use Brick\Money\Currency;

final readonly class InvestmentConfigurationCurrency
{
    private function __construct(private Currency $currency) {}

    public static function fromCode(string $code): self
    {
        return new self(Currency::of($code));
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function code(): string
    {
        return $this->currency->getCurrencyCode();
    }
}
