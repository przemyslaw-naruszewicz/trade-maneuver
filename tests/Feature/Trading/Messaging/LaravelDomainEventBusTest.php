<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Messaging;

use Illuminate\Events\Dispatcher;
use Tests\Feature\Trading\Messaging\Support\HandledTestDomainEvents;
use Tests\Feature\Trading\Messaging\Support\TestDomainEvent;
use Tests\Feature\Trading\Messaging\Support\TestDomainEventHandlerA;
use Tests\Feature\Trading\Messaging\Support\TestDomainEventHandlerB;
use Tests\TestCase;
use TradeManeuver\Trading\Application\Audit\AuditContext;
use TradeManeuver\Trading\Infrastructure\Messaging\LaravelDomainEventBus;

final class LaravelDomainEventBusTest extends TestCase
{
    private HandledTestDomainEvents $handled;

    private LaravelDomainEventBus $bus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handled = new HandledTestDomainEvents;
        $this->app->instance(HandledTestDomainEvents::class, $this->handled);
        $this->bus = new LaravelDomainEventBus(new Dispatcher($this->app));
    }

    public function test_the_bus_calls_the_handlers_of_an_event_before_it_returns_in_the_order_of_their_registration(): void
    {
        $this->bus->listen(TestDomainEvent::class, TestDomainEventHandlerB::class);
        $this->bus->listen(TestDomainEvent::class, TestDomainEventHandlerA::class);
        $event = new TestDomainEvent('first');
        $context = new AuditContext('test', ['note' => 'A note.']);

        $this->bus->dispatch($context, $event);

        $this->assertSame([['B', $event, $context], ['A', $event, $context]], $this->handled->calls);
    }

    public function test_an_event_without_a_handler_is_dispatched_without_effect(): void
    {
        $this->bus->dispatch(new AuditContext('test'), new TestDomainEvent('unhandled'));

        $this->assertSame([], $this->handled->calls);
    }
}
