<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Messaging\Support;

use TradeManeuver\Trading\Application\DomainEvent\DomainEventContext;

final readonly class TestDomainEventHandlerA
{
    public function __construct(
        private HandledTestDomainEvents $handled,
    ) {}

    public function handle(TestDomainEvent $event, DomainEventContext $context): void
    {
        $this->handled->calls[] = ['A', $event, $context];
    }
}
