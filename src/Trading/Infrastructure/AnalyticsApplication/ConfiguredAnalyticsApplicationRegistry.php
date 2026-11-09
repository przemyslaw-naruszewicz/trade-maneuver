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
    /** @var array<string, string> the RabbitMQ user of each known analytics application */
    private array $rabbitMqUsers;

    /**
     * @param  array<mixed>  $entries
     */
    public function __construct(array $entries)
    {
        $rabbitMqUsers = [];
        foreach ($entries as $entry) {
            $rabbitMqUsers[self::stringOf($entry, 'id')] = self::stringOf($entry, 'rabbitmq_user');
        }
        $this->rabbitMqUsers = $rabbitMqUsers;
    }

    public function isKnown(AnalyticsApplicationId $analyticsApplication): bool
    {
        return array_key_exists($analyticsApplication->toString(), $this->rabbitMqUsers);
    }

    public function publishesAs(AnalyticsApplicationId $analyticsApplication, string $publisherIdentity): bool
    {
        return ($this->rabbitMqUsers[$analyticsApplication->toString()] ?? null) === $publisherIdentity;
    }

    public function outcomeRoutingKey(AnalyticsApplicationId $analyticsApplication): ?string
    {
        return $this->isKnown($analyticsApplication) ? $analyticsApplication->toString() : null;
    }

    private static function stringOf(mixed $entry, string $key): string
    {
        $value = is_array($entry) ? ($entry[$key] ?? null) : null;
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf('Each entry in trading.analytics_applications needs a non-empty string "%s".', $key));
        }

        return $value;
    }
}
