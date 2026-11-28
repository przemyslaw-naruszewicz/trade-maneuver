<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging;

use Illuminate\Contracts\Events\Dispatcher;
use TradeManeuver\Trading\Application\DomainEvent\DomainEventContext;
use TradeManeuver\Trading\Application\Port\DomainEventBusInterface;

/**
 * Dispatches domain events through a Laravel event dispatcher of their own, so no framework or wildcard listener
 * receives them and faking the framework's events in a test does not swallow them. Handlers run synchronously in the
 * order of their registration and receive the event and the context.
 */
final readonly class LaravelDomainEventBus implements DomainEventBusInterface
{
    public function __construct(
        private Dispatcher $dispatcher,
    ) {}

    /**
     * @param  class-string  $event
     * @param  class-string  $handler  a class whose handle() takes the event and the context
     */
    public function listen(string $event, string $handler): void
    {
        $this->dispatcher->listen($event, $handler);
    }

    public function dispatch(DomainEventContext $context, object ...$events): void
    {
        foreach ($events as $event) {
            // Dispatched by name, because Laravel passes an event object alone and drops the rest of the payload.
            $this->dispatcher->dispatch($event::class, [$event, $context]);
        }
    }
}
