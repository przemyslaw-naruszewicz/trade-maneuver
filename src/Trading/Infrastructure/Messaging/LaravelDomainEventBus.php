<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging;

use Illuminate\Contracts\Events\Dispatcher;
use TradeManeuver\Trading\Application\DomainEvent\DomainEventContext;
use TradeManeuver\Trading\Application\Port\DomainEventBusInterface;

final readonly class LaravelDomainEventBus implements DomainEventBusInterface
{
    public function __construct(
        private Dispatcher $dispatcher,
    ) {}

    /**
     * @param  class-string  $event
     * @param  class-string  $handler
     */
    public function listen(string $event, string $handler): void {}

    public function dispatch(DomainEventContext $context, object ...$events): void {}
}
