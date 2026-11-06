<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\AnalyticsApplication;

use InvalidArgumentException;
use TradeManeuver\Trading\Application\Port\AnalyticsApplicationRegistryInterface;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;

/**
 * Reads the registry from `trading.analytics_applications`; an invalid entry fails at startup of the use case.
 */
final readonly class ConfiguredAnalyticsApplicationRegistry implements AnalyticsApplicationRegistryInterface
{
    /** @var list<string> */
    private array $knownIdentifiers;

    /**
     * @param  array<mixed>  $entries
     */
    public function __construct(array $entries)
    {
        $knownIdentifiers = [];
        foreach ($entries as $entry) {
            $knownIdentifiers[] = self::identifierOf($entry);
        }
        $this->knownIdentifiers = $knownIdentifiers;
    }

    public function isKnown(AnalyticsApplicationId $analyticsApplication): bool
    {
        return in_array($analyticsApplication->toString(), $this->knownIdentifiers, true);
    }

    private static function identifierOf(mixed $entry): string
    {
        $identifier = is_array($entry) ? ($entry['id'] ?? null) : null;
        if (! is_string($identifier) || trim($identifier) === '') {
            throw new InvalidArgumentException('Each entry in trading.analytics_applications needs a non-empty string "id".');
        }

        return $identifier;
    }
}
