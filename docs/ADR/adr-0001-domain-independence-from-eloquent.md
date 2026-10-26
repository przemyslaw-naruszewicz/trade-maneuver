# ADR-0001: Keep domain aggregates independent of Eloquent

Date: 2026-10-26

Status: Accepted as the target design; implementation is pending.

## Context

The service requires a rich domain model independent of Laravel while retaining Eloquent Active Record for persistence. Using an Eloquent model directly as an aggregate root would couple business objects to the ORM through inheritance and persistence APIs.

We want to test business behavior independently and make it easier to reuse the domain under another framework, including Symfony. A separate persistence representation adds mapping code and maintenance work.

## Decision

Keep domain aggregates as plain PHP objects. An aggregate root such as `Domain/Order/Order.php` owns business behavior and invariants and does not extend `Illuminate\Database\Eloquent\Model`.

Keep Eloquent models, such as `Infrastructure/Order/Eloquent/OrderModel.php`, in infrastructure adapters. They use native Active Record capabilities and represent persisted state. Domain repository interfaces expose domain objects; infrastructure implementations translate between domain aggregates and Eloquent models.

Maintain mapping code in repositories as the cost of domain independence. Eloquent remains the ORM; we do not replace it with Doctrine or build a generic Data Mapper framework. Reconstitution validates stored aggregate state without replaying business actions, applying current eligibility policies retroactively, or emitting new domain events. Incompatible stored state requires an explicit migration or compatibility policy.

Enforce the separation with Deptrac and verify repository translation with integration tests. Separate domain and persistence classes satisfy this project's framework-independence requirement; DDD also permits other implementations.

## Alternatives considered

- Use Eloquent models directly as domain aggregates. This avoids a second representation and manual mapping, but makes the domain depend on Eloquent. It would require relaxing the framework-independence requirement.
- Use Doctrine to persist domain objects through ORM mapping. This delegates mapping to a Data Mapper ORM, but changes the selected persistence technology and conflicts with retaining Eloquent.

## Consequences and trade-offs

- Domain behavior and its unit tests can run without Laravel or a database.
- Moving to another framework can preserve domain objects and repository contracts, subject to PHP and library compatibility. Framework adapters and persistence integration still require implementation and validation.
- Domain state and persistence state have separate representations. Changes require maintaining their translation and testing that values and aggregate contents survive persistence correctly.
- Native Eloquent features remain available in infrastructure. Domain behavior cannot directly rely on Eloquent relationships, casts, observers, or persistence methods.
- Separating classes does not by itself protect aggregate invariants, transaction atomicity, or concurrent writes.

The directory layout and Laravel registration rules remain in [architecture.md](../architecture.md#directory-structure-and-layers). Transaction and concurrency requirements remain in its [aggregate boundaries](../architecture.md#aggregate-and-transaction-boundaries) and [concurrency control](../architecture.md#concurrency-control) sections.

[ADR-0002](adr-0002-aggregate-state-for-eloquent-persistence.md) specifies `id()` and an immutable `state()` contract for repository mapping.
