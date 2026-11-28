<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Messaging\Support;

use TradeManeuver\Trading\Application\Audit\AuditContext;

/**
 * Collects, in the order of the calls, which test handler received which event with which context.
 */
final class HandledTestDomainEvents
{
    /**
     * @var list<array{string, TestDomainEvent, AuditContext}>
     */
    public array $calls = [];
}
