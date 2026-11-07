<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Port;

use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;

/**
 * The known analytics applications, supplied as deployment configuration.
 */
interface AnalyticsApplicationRegistryInterface
{
    public function isKnown(AnalyticsApplicationId $analyticsApplication): bool;

    /**
     * Whether the publisher identity, the RabbitMQ user that published a message, is the one registered for the
     * analytics application.
     */
    public function publishesAs(AnalyticsApplicationId $analyticsApplication, string $publisherIdentity): bool;
}
