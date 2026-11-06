<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject;

use Brick\Money\Currency;
use Brick\Money\Money;

final readonly class OrderAmount
{
    private function __construct(private Money $money) {}

    public static function of(string $amount, string $currencyCode): self
    {
        return new self(Money::of($amount, $currencyCode));
    }

    public function money(): Money
    {
        return $this->money;
    }

    public function currency(): Currency
    {
        return $this->money->getCurrency();
    }

    /**
     * The amount at the currency's scale, for persistence and serialization.
     */
    public function amount(): string
    {
        return $this->money->getAmount()->toString();
    }
}
