<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject;

use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Currency;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception\InvalidOrderAmountException;

/**
 * Money greater than zero, kept at its currency's scale without rounding.
 */
final readonly class OrderAmount
{
    private function __construct(private Money $money) {}

    /**
     * @throws InvalidOrderAmountException
     */
    public static function of(string $amount, string $currencyCode): self
    {
        if (! PlainDecimal::matches($amount)) {
            throw new InvalidOrderAmountException('The order amount must be a decimal number, such as 200.00.');
        }

        $currency = self::currencyOf($currencyCode);
        try {
            $money = Money::of($amount, $currency);
        } catch (RoundingNecessaryException) {
            throw new InvalidOrderAmountException(sprintf(
                'The order amount must have at most %d decimal places in %s.',
                $currency->getDefaultFractionDigits(),
                $currency->getCurrencyCode(),
            ));
        }

        if (! $money->isPositive()) {
            throw new InvalidOrderAmountException('The order amount must be greater than zero.');
        }

        return new self($money);
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

    private static function currencyOf(string $currencyCode): Currency
    {
        try {
            return Currency::of($currencyCode);
        } catch (UnknownCurrencyException) {
            throw new InvalidOrderAmountException(sprintf('The currency "%s" is unknown.', $currencyCode));
        }
    }
}
