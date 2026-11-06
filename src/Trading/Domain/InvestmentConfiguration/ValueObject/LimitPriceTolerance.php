<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\RoundingNecessaryException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception\InvalidLimitPriceToleranceException;

/**
 * An exact decimal percentage from 0 % to 5 % inclusive, with at most two decimal places.
 */
final readonly class LimitPriceTolerance
{
    private const int SCALE = 2;

    private const string MINIMUM = '0';

    private const string MAXIMUM = '5';

    private function __construct(private BigDecimal $percentage) {}

    /**
     * @throws InvalidLimitPriceToleranceException
     */
    public static function fromPercentage(string $percentage): self
    {
        if (! PlainDecimal::matches($percentage)) {
            throw new InvalidLimitPriceToleranceException('The limit price tolerance must be a decimal percentage, such as 1.50.');
        }

        try {
            $value = BigDecimal::of($percentage)->toScale(self::SCALE);
        } catch (RoundingNecessaryException) {
            throw new InvalidLimitPriceToleranceException(sprintf(
                'The limit price tolerance must have at most %d decimal places.',
                self::SCALE,
            ));
        }

        if ($value->isLessThan(self::MINIMUM) || $value->isGreaterThan(self::MAXIMUM)) {
            throw new InvalidLimitPriceToleranceException('The limit price tolerance must be from 0 % to 5 % inclusive.');
        }

        return new self($value);
    }

    public function percentage(): BigDecimal
    {
        return $this->percentage;
    }

    /**
     * The percentage at its fixed scale, for persistence and serialization.
     */
    public function toString(): string
    {
        return $this->percentage->toString();
    }
}
