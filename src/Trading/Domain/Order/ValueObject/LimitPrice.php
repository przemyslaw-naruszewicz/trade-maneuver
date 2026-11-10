<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Order\ValueObject;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Currency;
use Brick\Money\Money;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\LimitPriceTolerance;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ReferencePrice;

final readonly class LimitPrice
{
    private function __construct(private Money $money) {}

    public static function proposedFor(ReferencePrice $referencePrice, LimitPriceTolerance $tolerance): self
    {
        return new self(Money::of(BigDecimal::of($referencePrice->amount()), Currency::of($referencePrice->currencyCode()), roundingMode: RoundingMode::Down));
    }

    public function amount(): string
    {
        return $this->money->getAmount()->toString();
    }

    public function currencyCode(): string
    {
        return $this->money->getCurrency()->getCurrencyCode();
    }
}
