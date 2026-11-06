<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject;

use Brick\Math\BigDecimal;

final readonly class LimitPriceTolerance
{
    private const int SCALE = 2;

    private function __construct(private BigDecimal $percentage) {}

    public static function fromPercentage(string $percentage): self
    {
        return new self(BigDecimal::of($percentage)->toScale(self::SCALE));
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
