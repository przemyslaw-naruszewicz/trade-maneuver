<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Order\ValueObject;

use TradeManeuver\Trading\Domain\Order\Exception\InvalidOrderValueException;

/**
 * The identity that Trade Maneuver gives an order: a UUID in canonical lower-case form.
 */
final readonly class OrderId
{
    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D';

    /**
     * @throws InvalidOrderValueException
     */
    public function __construct(private string $value)
    {
        if (preg_match(self::UUID_PATTERN, $value) !== 1) {
            throw new InvalidOrderValueException(sprintf('The order identity "%s" is not a UUID.', $value));
        }
    }

    public function toString(): string
    {
        return $this->value;
    }
}
