# ADR-0004: Read through Eloquent read models in Livewire

Date: 2026-11-02

Status: Accepted as the target design; implementation is pending.

## Context

[architecture.md](../architecture.md#directory-structure-and-layers) does not allow `Ui` to depend on `Infrastructure`, so each Livewire screen would need a query port, an adapter, DTOs, and a framework-independent pagination contract, although these reads hold no business rules.

Trade Maneuver should show DDD and native use of Laravel together. [ADR-0001](adr-0001-domain-independence-from-eloquent.md) rules out native Eloquent use in aggregates, but GUI reads have no invariants to protect.

## Decision

Allow Livewire components in `Ui/Livewire` to read through read-only Eloquent read models in `Infrastructure/{Aggregate}/ReadModel`, without a query port or query DTOs. Read models are separate from write-side models and throw an exception on any write. State changes still go through application commands and aggregates, and a read model is never the basis for a write-side decision. Other consumers keep using `{Aggregate}QueryInterface` and DTOs.

The detailed rules are in [architecture.md](../architecture.md#cqrs).

## Alternatives considered

- Keep a query port with DTOs for every screen. This is the strict hexagonal form: the data source can change without touching the component, and a component can be tested with a fake. It costs a port, DTOs, and a pagination contract for reads with no business rules.
- Place read models in `Ui`. This removes the dependency between adapters, but puts persistence in `Ui`, against ADR-0001.
- Use the write-side models in Livewire. This needs no extra classes, but gives the GUI `save()`, `update()`, and `delete()`, which can bypass aggregate behavior.

## Consequences and trade-offs

- `Domain` and `Application` stay independent of Laravel, and ADR-0001 does not change. [ADR-0002](adr-0002-aggregate-state-for-eloquent-persistence.md) keeps its `state()` contract, but its statement that GUI reads use query DTOs no longer applies to Livewire.
- A component cannot be tested with a fake query, and changing the data source means changing the component. Feature tests run these screens against real PostgreSQL.
- Screens depend on the table structure, and a table can have two Eloquent classes, one for writing and one for reading.
- Deptrac enforces who depends on read models but cannot check that they reject writes, so tests must cover that.
- Operator authentication, TOTP, and output escaping stay as they are.
