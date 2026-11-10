<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Order\ValueObject;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Currency;
use Brick\Money\Money;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\LimitPriceTolerance;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\OrderAmount;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ReferencePrice;

/**
 * The worst price at which a buy order may be executed, at its currency's minor unit.
 */
final readonly class LimitPrice
{
    private function __construct(private Money $money) {}

    /**
     * The reference price raised by the tolerance and rounded down to the currency's minor unit, so that the limit
     * price never exceeds the tolerance (ADR-0005).
     */
    public static function proposedFor(ReferencePrice $referencePrice, LimitPriceTolerance $tolerance): self
    {
        $unrounded = $tolerance->addedTo(BigDecimal::of($referencePrice->amount()));

        return new self(Money::of($unrounded, Currency::of($referencePrice->currencyCode()), roundingMode: RoundingMode::Down));
    }

    public static function of(string $amount, string $currencyCode): self
    {
        return new self(Money::of($amount, $currencyCode));
    }

    public function unitsAffordableWith(OrderAmount $orderAmount): int
    {
        return 1;
    }

    /**
     * The amount at the currency's scale, for persistence and serialization.
     */
    public function amount(): string
    {
        return $this->money->getAmount()->toString();
    }

    public function currencyCode(): string
    {
        return $this->money->getCurrency()->getCurrencyCode();
    }
}
