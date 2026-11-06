<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject;

use TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception\InvalidInvestmentConfigurationIdException;

final readonly class InvestmentConfigurationId
{
    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D';

    /**
     * @throws InvalidInvestmentConfigurationIdException
     */
    public function __construct(private string $value)
    {
        if (preg_match(self::UUID_PATTERN, $value) !== 1) {
            throw new InvalidInvestmentConfigurationIdException(sprintf('The investment configuration identity "%s" is not a UUID.', $value));
        }
    }

    public function toString(): string
    {
        return $this->value;
    }
}
