# ADR-0002: Expose immutable aggregate state for Eloquent persistence

Date: 2026-10-26

Status: Accepted as the target design; implementation is pending.

## Context

[ADR-0001](adr-0001-domain-independence-from-eloquent.md) keeps domain aggregates independent of Laravel and uses separate Eloquent models in infrastructure. Repositories therefore need an explicit way to read aggregate state and translate it into the persistence representation.

Eloquent persists its own Active Record models. Mapping separate domain objects requires access to their state. We want a typed read contract and do not want to maintain a custom reflection-based persistence mechanism.

We follow Vaughn Vernon's approach to aggregate consistency boundaries and invariant protection. The state accessor is our persistence compromise; neither that approach nor Laravel requires it.

## Decision

Expose the aggregate identity through `id()` and the remaining persisted domain state through one `state()` method. For example, `Order::id(): OrderId` returns the identifier and `Order::state(): OrderStateDto` returns the state representation.

Define `{Aggregate}StateDto` in `Domain/{Aggregate}/Dto`. It is a framework-independent, immutable contract expressed in domain terms. Keep the identity separate and include only the state needed for persistence and reconstitution. Do not include Eloquent objects, database column names, or transport-specific fields.

Every value reachable through the DTO must be immutable or a detached immutable representation. Prefer value objects, enums, and typed child-state DTOs; never return live child entities or mutable collections. A `readonly` DTO alone does not make its referenced objects immutable. Previously returned state must remain unchanged when the aggregate later changes.

Infrastructure repositories explicitly map `id()` and `state()` to Eloquent models. Do not use reflection to extract aggregate state for routine persistence. Keep state changes behind domain behavior such as `confirm()` and `cancel()`; do not add public setters or per-field getters for persistence.

Treat `state()` as an internal persistence contract. Application workflows and domain collaborators must not use it to take over an aggregate's business decisions. GUI/API reads use query DTOs, and integration events have separate public contracts. Reconstitution is a controlled repository/factory path that validates stored state without replaying business actions, applying current eligibility policies retroactively, or emitting new domain events. It must not become a general way to replace a live aggregate's state. Incompatible stored state requires an explicit migration or compatibility policy.

## Alternatives considered

- Individual public getters provide explicit, statically analyzable access and can expose less information when only a few values are needed. We choose a single state contract for repository mapping to avoid growing a public accessor method for every persisted field.
- Reflection-based extraction keeps state out of the public interface, but couples mapping to private field names and moves more failures to runtime. We choose an explicit typed contract instead of maintaining this mechanism alongside Eloquent.

## Consequences and trade-offs

- Repository mapping is straightforward and can be checked through types and tests without a custom reflective mapper.
- The domain stays independent of Laravel, while its public API accommodates the needs of the selected Laravel/Eloquent persistence approach.
- The aggregate controls mutation while exposing a representation of its internal state. This accessor may reveal more information than several narrow getters.
- Public visibility does not restrict access to repositories. Limiting production use to persistence mapping is a project convention requiring review or a dedicated static-analysis rule; layer checks alone do not enforce method-level access.
- Changes to persisted domain state require maintaining the DTO and its repository mapping. The DTO must not become a convenient general read model for other parts of the application.
- The contract does not replace aggregate-root transaction boundaries, optimistic version checks, pessimistic locks, or idempotency mechanisms.

Verify that returned state cannot mutate the aggregate, remains unchanged after later aggregate operations, and preserves values and child state through persistence and reconstitution. Tests must also confirm that reconstitution does not emit new domain events. General transaction and concurrency requirements remain in [architecture.md](../architecture.md#aggregate-and-transaction-boundaries).
