<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\ValueObject;

use Brick\Math\BigDecimal;
use Brick\Money\Currency;
use Brick\Money\Exception\UnknownCurrencyException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\PlainDecimal;
use TradeManeuver\Trading\Domain\TradeSignal\Exception\InvalidTradeSignalValueException;

/**
 * The instrument price an analytics application reports, with its currency and the time it applies to.
 * The amount keeps the scale it was sent with, which may exceed the currency's minor unit.
 */
final readonly class ReferencePrice
{
    private function __construct(private BigDecimal $amount, private Currency $currency, private UtcTimestamp $asOf) {}

    /**
     * @throws InvalidTradeSignalValueException
     */
    public static function of(string $amount, string $currencyCode, string $asOf): self
    {
        if (! PlainDecimal::matches($amount) || ! BigDecimal::of($amount)->isPositive()) {
            throw new InvalidTradeSignalValueException('The reference price amount must be a decimal string greater than zero, such as 12.50.', 'amount');
        }

        try {
            $currency = Currency::of($currencyCode);
        } catch (UnknownCurrencyException) {
            throw new InvalidTradeSignalValueException('The reference price currency must be an ISO 4217 code, such as EUR.', 'currency');
        }

        try {
            $time = UtcTimestamp::fromString($asOf);
        } catch (InvalidTradeSignalValueException $exception) {
            throw new InvalidTradeSignalValueException($exception->getMessage(), 'asOf');
        }

        return new self(BigDecimal::of($amount), $currency, $time);
    }

    public function amount(): string
    {
        return $this->amount->toString();
    }

    public function currencyCode(): string
    {
        return $this->currency->getCurrencyCode();
    }

    public function asOf(): UtcTimestamp
    {
        return $this->asOf;
    }
}
