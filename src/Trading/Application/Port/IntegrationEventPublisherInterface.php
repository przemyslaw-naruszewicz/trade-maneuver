<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Port;

use TradeManeuver\Trading\Application\IntegrationEvent\SignalOutcomeIntegrationEvent;

/**
 * Records publication intent in the transactional outbox of the caller's transaction; it never delivers inline.
 */
interface IntegrationEventPublisherInterface
{
    public function publish(SignalOutcomeIntegrationEvent $event): void;
}
