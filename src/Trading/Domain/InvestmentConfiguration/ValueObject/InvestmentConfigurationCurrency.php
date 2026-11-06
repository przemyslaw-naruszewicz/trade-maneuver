<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject;

use Brick\Money\Currency;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception\UnsupportedCurrencyException;

/**
 * The one currency of an investment configuration: EUR in this iteration.
 */
final readonly class InvestmentConfigurationCurrency
{
    private const string SUPPORTED_CODE = 'EUR';

    private function __construct(private Currency $currency) {}

    /**
     * @throws UnsupportedCurrencyException
     */
    public static function fromCode(string $code): self
    {
        if ($code !== self::SUPPORTED_CODE) {
            throw new UnsupportedCurrencyException(sprintf(
                'The investment configuration currency must be %s, not "%s".',
                self::SUPPORTED_CODE,
                $code,
            ));
        }

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
