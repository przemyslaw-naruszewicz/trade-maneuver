<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Messaging\Support;

use TradeManeuver\Trading\Application\Audit\AuditContext;

final readonly class TestDomainEventHandlerA
{
    public function __construct(
        private HandledTestDomainEvents $handled,
    ) {}

    public function handle(TestDomainEvent $event, AuditContext $context): void
    {
        $this->handled->calls[] = ['A', $event, $context];
    }
}
