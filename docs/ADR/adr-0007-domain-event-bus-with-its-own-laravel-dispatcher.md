# ADR-0007: Domain event bus on a Laravel dispatcher of its own, with an audit context per dispatch

Date: 2026-11-28

Status: Accepted

## Context

[The architecture](../architecture.md#domain-events) dispatches domain events to their local reactions synchronously, in the command's transaction, through `DomainEventBusInterface` and an infrastructure adapter. TM-012A implements the bus and moves the audit records, signal outcome messages, and log entries of every recorded domain event into handlers. Two questions came up that the architecture does not answer.

The first is which Laravel dispatcher the adapter uses. The application's global dispatcher also delivers framework events, accepts wildcard listeners, and is replaced by `Event::fake()` in tests.

The second is where a handler gets data that the use case holds but the event does not: the audit actor, the message metadata at intake, and the operator's note at refusal. TM-012A decides that events keep only domain facts, so the operator's note does not become part of `OrderRefused`.

TM-012A answers the second question. The implementation of TM-012A in PR #57 answers the first, and the project owner chose the name and location of the context in that PR.

## Decision

`LaravelDomainEventBus` dispatches through an `Illuminate\Events\Dispatcher` instance of its own, which `TradingServiceProvider` creates as a singleton. The provider registers every handler in `boot()` from one map, `DOMAIN_EVENT_HANDLERS`, in the order the bus calls them. The adapter dispatches each event by its class name with the event and the context as the payload, because Laravel passes an event object to its listeners alone.

Each dispatch carries an `AuditContext` from `Application/Audit/`, with the audit actor and the extra audit values of the use case. Named constructors set the actor format: `analytics-application:<id>`, `operator:<id>`, and `trade-maneuver`. The context holds nothing else.

## Alternatives considered

- The global dispatcher with `Event::listen()`. It is Laravel's usual listener registration, but framework and wildcard listeners would receive domain events, and a test that fakes the framework's events would silently drop the audit records and signal outcome messages.
- Queued listeners. They would run after the transaction, so an audit record or outbox entry could be lost or arrive without its aggregate change. The architecture forbids them for local reactions.
- Calling the handlers directly from the use cases. Every new path that ends a trade signal or changes an order would have to remember each reaction, which is what TM-012A removes.
- Adding the actor and the extra values to the domain events. The events would carry transport metadata and command data that are not domain facts.

## Consequences

- One map in `TradingServiceProvider` lists every reaction to a domain event. A listener registered anywhere else, including on the global dispatcher, never receives one.
- Tests that fake Laravel's events still run the domain event handlers.
- A handler that needs a value its event lacks gets it through `AuditContext` only if it is an audit value. Correlation and causation identifiers need their own decision and do not go into this context.
