<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject;

use Brick\Math\BigDecimal;
use Brick\Money\Currency;
use Brick\Money\Exception\UnknownCurrencyException;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\Exception\InvalidTradeSignalException;

/**
 * An invented instrument price with its ISO 4217 currency and the time it applies to. The amount is kept as it was
 * given.
 */
final readonly class ReferencePrice
{
    private const string AMOUNT_PATTERN = '/^\d+(\.\d+)?$/D';

    private Currency $currency;

    /**
     * @throws InvalidTradeSignalException
     */
    public function __construct(private string $amount, string $currencyCode, private UtcTime $asOf)
    {
        if (preg_match(self::AMOUNT_PATTERN, $amount) !== 1 || BigDecimal::of($amount)->isZero()) {
            throw new InvalidTradeSignalException('The reference price must be a decimal greater than zero, such as 12.50.');
        }

        try {
            $this->currency = Currency::of($currencyCode);
        } catch (UnknownCurrencyException) {
            throw new InvalidTradeSignalException('The currency must be an ISO 4217 code, such as EUR.');
        }
    }

    public function amount(): string
    {
        return $this->amount;
    }

    public function currencyCode(): string
    {
        return $this->currency->getCurrencyCode();
    }

    public function asOf(): UtcTime
    {
        return $this->asOf;
    }
}
