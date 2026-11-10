<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Order\ValueObject;

use TradeManeuver\Trading\Domain\Order\Exception\InvalidOrderValueException;

/**
 * A whole number of units, at least one: there are no fractional units.
 */
final readonly class OrderQuantity
{
    private function __construct(private int $units) {}

    /**
     * @throws InvalidOrderValueException
     */
    public static function of(int $units): self
    {
        if ($units < 1) {
            throw new InvalidOrderValueException(sprintf('The order quantity must be at least one unit, not %d.', $units));
        }

        return new self($units);
    }

    public function units(): int
    {
        return $this->units;
    }
}
