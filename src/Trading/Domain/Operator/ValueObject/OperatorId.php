<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Operator\ValueObject;

use TradeManeuver\Trading\Domain\Operator\Exception\InvalidOperatorValueException;

final readonly class OperatorId
{
    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D';

    /**
     * @throws InvalidOperatorValueException
     */
    public function __construct(private string $value)
    {
        if (preg_match(self::UUID_PATTERN, $value) !== 1) {
            throw new InvalidOperatorValueException(sprintf('The operator identity "%s" is not a UUID.', $value));
        }
    }

    public function toString(): string
    {
        return $this->value;
    }
}
