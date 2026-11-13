# Domain-driven design

Use Vaughn Vernon's interpretation of DDD as the primary reference for modeling and implementation reviews. Where the two differ, this project follows Vernon rather than Eric Evans's original presentation. Shared DDD terms retain their established meaning.

Use DDD Crew's collaborative modeling tools to discover and test the model against business scenarios. Framework independence, CQRS, Eloquent integration, and the `state()` contract are project choices, not universal requirements of DDD. These rules describe the target model; the code and the done tasks show what is implemented.

## Relationship to other documents

- Use this document for modeling terminology, aggregate design, the placement of business behavior, encapsulation, and what reconstitution means.
- [architecture.md](architecture.md) defines the directory layout, dependency rules, framework integration, and transaction, locking, and messaging mechanisms.
- [coding-standards.md](coding-standards.md) defines how to write code: readability, comments, PHP usage, typing, and naming.
- [testing-strategy.md](testing-strategy.md) defines how behavior and architectural guarantees are verified, including the checks before delivering a write use case.

Update related documents when a decision changes. Record significant departures and their reasons in `docs/ADR`, preserving the history of accepted decisions.

## Domain discovery with DDD Crew

Start from business capabilities, concrete scenarios, terminology, decisions, and failure cases. The project owner is the domain expert and defines the business rules for execution and analytics. Reuse established decisions and resolve open business questions directly with the owner. Refine the model as examples reveal missing rules or misleading boundaries.

Keep relevant conclusions close to the implementation or its ADR. Every context and aggregate definition must be backed by business scenarios. Use the canvases listed under [Modeling resources](#modeling-resources) to investigate those scenarios within the project rules below. Their workshop formats and optional patterns supplement Vernon's modeling guidance; they do not override our aggregate rules or select a persistence or messaging architecture for us.

## Strategic boundaries and language

- A subdomain describes a business problem area; a bounded context defines where one model and language apply consistently. A directory or deployable service does not establish that boundary by itself.
- Classify capabilities as core, supporting, or generic based on their business role. Trade Maneuver is not automatically the core domain simply because it is the current repository.
- Maintain a ubiquitous language within each context. Use the same terms in conversations, commands, domain methods, events, tests, and documentation. Resolve ambiguous terms through business examples rather than technical naming conventions.
- Keep analytical decision-making in the analytics applications. Trade Maneuver owns the evaluation of trade signals and the lifecycle and outcomes of its orders. The first use case is modeled in a single Trading context; further boundaries remain a modeling decision to be established from actual use cases.
- Own data and models within their contexts. Define public contracts and use an anti-corruption layer where another context's terminology or representation needs translation. Do not share internal entities, repositories, or direct database access across boundaries.
- A shared kernel, if introduced, must be a small, deliberately shared domain model with explicit ownership and change coordination. Reusable technical helpers do not automatically form a shared kernel.
- Use Ordering, Party, Inventory, and Product Catalog as reference archetypes when their responsibilities fit the business. They do not mandate four contexts, four services, or implementation of capabilities that this service does not own.

## Tactical vocabulary

| Concept | Meaning in this project |
| --- | --- |
| Entity | A domain object whose identity continues across changes to its attributes. It owns relevant behavior. |
| Value object | An immutable domain value defined by its contents, with validation and meaningful operations. Prefer it over interchangeable primitive arguments. |
| Aggregate | The consistency boundary around a root and the entities/value objects needed to protect its immediate business invariants. It may contain only one entity. |
| Aggregate root | The entry point for changing an aggregate. It controls invariant-preserving operations on internal parts. |
| Invariant | A business condition that must hold at the aggregate's successful commit, including under concurrent access. |
| Repository | A domain contract for retrieving and persisting aggregate roots. It does not expose ORM objects or offer independent writes to internal child entities. |
| Factory | A domain construction mechanism that creates valid objects when construction deserves a separate responsibility. |
| Domain service or policy | A business operation or decision that has no natural home in an entity or value object. It must not become a container for behavior stripped from aggregates. |
| Domain event | An immutable fact recorded by the domain when meaningful business behavior occurs. Its publication and integration translation follow the architecture rules. |
| Application handler | A use-case coordinator that loads dependencies, invokes domain behavior, and arranges persistence within the chosen transaction boundary. |
| Orchestrator | An explicit application process coordinating participants, progress, retries, and compensation across separate operations. |

An aggregate may span several classes and database rows. Its boundary follows business invariants, not the Eloquent model layout. Distinct root identities within an aggregate type and bounded context represent different aggregates; two loaded objects with the same contextual identity represent the same aggregate and may contain different versions of its state.

## Aggregate design following Vernon

Use the four guiding rules summarized in Vernon's [Effective Aggregate Design, Part III](https://www.dddcommunity.org/wp-content/uploads/files/pdf_articles/Vernon_2011_3.pdf): discover actual invariants, design small aggregates, reference other aggregates by identity, and use eventual consistency outside the consistency boundary.

- Explain why each internal entity belongs to the aggregate using a concrete immediate invariant. Include enough state to enforce that rule, and avoid large object graphs justified only by convenient navigation.
- Model explicit business transitions. A request such as `confirm()` must check its own preconditions and preserve the aggregate's rules; callers must not reconstruct that decision through state inspection and setters.
- A command normally changes one business aggregate instance per transaction, including its internal entities and value objects. Reading another aggregate does not give permission to change it in that transaction. Revisit the model when an immediate invariant appears to span aggregates or a use case repeatedly needs a wider atomic boundary; document a justified exception in an ADR.
- Refer to other aggregates through typed identities. Within one aggregate, child entities can have identity and behavior, but external callers must not mutate them independently of the root.
- Orchestration is the default for workflows involving independent aggregates. Coordinate them through explicit application orchestrators and separate transactions. Define pending, successful, rejected, and recoverable outcomes according to the business process. This is a project decision; Vernon's [process-management guidance](https://kalele.io/training/fast-track-process/) covers both orchestration and choreography.
- Separate immediate invariants from policies that can tolerate temporary inconsistency. Use the project owner's business rules to determine whether a delay is acceptable; clarify this directly with the owner when the rule is unresolved. For a corrective policy, name the acceptable delay, responsible process, and recovery behavior. Never relax a hard limit merely because asynchronous delivery is convenient.
- Assess contention and aggregate growth while choosing boundaries. Document which invariant each lock or version check protects. Technical locking details and whole-transaction retries follow [architecture.md](architecture.md#concurrency-control).

When using the Aggregate Design Canvas, adapt size estimates to our state-based persistence: consider loaded rows, child collections, and transaction cost. Its event-growth questions do not require adopting event sourcing. Recording domain events or an audit trail alone does not make this service event-sourced.

## Placement of business behavior

- Entities and aggregates own their state transitions. Value objects own their validation, equality, and relevant calculations.
- Domain services and policies express business decisions in domain terms. Application code may resolve dependencies and supply relevant facts, but must not take over the decision itself. Facts read from elsewhere do not acquire stronger consistency merely because a domain service consumes them.
- Handlers and orchestrators coordinate. Controllers and consumers adapt input. Infrastructure implements persistence and integrations. None of these responsibilities replaces the invariant checks owned by the domain.
- Validate transport structure at the boundary and business meaning in the domain. Construction and transitions must reject invalid domain values even when invoked without HTTP or a framework validator.
- Keep framework types and persistence behavior outside the domain under ADR-0001. Exact-money arithmetic and strong typing follow the corresponding architecture rules.

## Encapsulation and the persistence compromise

Protect both valid mutation and the model's public interface. The project accepts a specific read contract for persistence under ADR-0002: `id()` plus `state(): {Aggregate}StateDto`.

- Return a detached, deeply immutable representation expressed in domain types. Never expose live mutable children or collections.
- Use the state contract only for repository translation and in tests. Its visibility is public, so a PHPStan check reports any other call ([architecture.md](architecture.md#dependency-rules)). Code that needs an aggregate's values for another purpose, such as an audit record, takes them from a domain event or from the values it passed to the aggregate.
- The state DTO is an internal domain contract. It exposes persisted domain state, while all mutations stay inside aggregate behavior.
- Keep all business transitions behind domain behavior. Do not reconstruct decisions in application code from the state DTO, introduce public setters, or use reconstitution to overwrite a live aggregate arbitrarily.
- Keep query DTOs and integration contracts separate from persisted domain state. A persistence DTO must not become the default response model for GUI or APIs.

The state DTO exposes persisted domain state to simplify Eloquent mapping; it can reveal more information than narrow getters. Reconstitution validates stored state without replaying business actions, applying current eligibility policies retroactively, or emitting new domain events. Handle incompatible stored state through an explicit migration or compatibility policy.

## Communication and consistency

A command requests a change, a query retrieves information, and an event reports a fact. Identify the owner and meaning of each message before choosing its transport.

For important scenarios, use DDD Crew's message-flow technique to identify participants, message order, payloads, and query results. Include transaction boundaries, retry behavior, and the orchestrator responsible for recovery. The diagram can describe direct calls as well as message delivery.

Internal synchronous domain events and public asynchronous integration events have different contracts. Translate selected facts in the application layer and retain explicit orchestration. Use the existing [event and reliable-messaging rules](architecture.md#domain-events-and-integration-events) for publication, Inbox/Outbox, and receiver guarantees; do not infer successful business execution from message acceptance.

## Examples used in reviews

The aggregate names and boundaries in these examples are illustrative. Actual boundaries still need to be established from business use cases.

| Situation | Expected modeling | Drift to reject |
| --- | --- | --- |
| Approving an order | The handler invokes the order's approval behavior, which validates the transition. | The handler reads status and sets a new status after implementing the business rule itself. |
| Enforcing an owned reservation limit | The owning consistency boundary validates the limit and commits under the chosen concurrency protocol. | Two callers check a stale total independently and insert reservations that collectively exceed the limit. |
| Processing a new external payload | An adapter translates it into local types and invokes a use case. | Foreign payload fields and ORM types propagate through the domain. |
| Saving aggregate state | A repository maps the accepted immutable state contract to Eloquent models. | Application code edits child rows or uses the state DTO to bypass aggregate behavior. |

## Modeling resources

DDD Crew's [Starter Modelling Process](https://github.com/ddd-crew/ddd-starter-modelling-process) connects business discovery with implementation. Use its activities and canvases when they help answer a concrete modeling question. A short discussion or worked example with the project owner is sufficient when it resolves the question.

Use the following DDD Crew resources for specific modeling questions:

| Resource | How we use it |
| --- | --- |
| [Welcome to DDD](https://github.com/ddd-crew/welcome-to-ddd) | Establish shared vocabulary before debating implementation patterns. |
| [Core Domain Charts](https://github.com/ddd-crew/core-domain-charts) | Discuss business differentiation and complexity, and direct modeling effort toward the capabilities that matter most. |
| [Bounded Context Canvas](https://github.com/ddd-crew/bounded-context-canvas) | Clarify each context's purpose, language, responsibilities, collaborators, contracts, assumptions, and open questions. |
| [Context Mapping](https://github.com/ddd-crew/context-mapping) | Make model ownership, team dependencies, and translation between contexts explicit. |
| [Aggregate Design Canvas](https://github.com/ddd-crew/aggregate-design-canvas) | Challenge aggregate boundaries against transitions, invariants, corrective policies, contention, and expected growth. |
| [Domain Message Flow Modelling](https://github.com/ddd-crew/domain-message-flow-modelling) | Trace commands, queries, and events for one scenario, including the significant data and participants. |
