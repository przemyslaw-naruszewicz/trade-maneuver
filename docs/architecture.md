# Architecture

These rules describe the target architecture. The repository currently contains a Laravel skeleton with PostgreSQL integration. The bounded contexts, domain model, Inbox/Outbox, and architecture checks still need implementation. Accepted ADRs record design decisions; their status does not imply that the corresponding code exists.

## Service purpose

The execution microservice will receive instructions from an analytics application, validate them against its domain rules, execute them, and expose their status and outcomes. The analytics application owns analytical decision-making.

## Object-oriented programming

- Use object-oriented programming throughout the application.
- Encapsulate business behavior and state in objects with clear responsibilities.
- Protect invariants by allowing state changes only through domain behavior.
- Prefer composition, delegation, and explicit dependencies over inheritance. Avoid inheritance for code reuse; use it only where a genuine substitutable relationship or a framework contract requires it, such as Eloquent models.
- Follow SOLID: the Single Responsibility, Open/Closed, Liskov Substitution, Interface Segregation, and Dependency Inversion principles.
- Use established design patterns where they solve a concrete problem and make responsibilities explicit. Avoid adding abstractions without a domain or technical need.

## Code semantics, readability, and PHP

- Use names, types, and control flow that express business intent in the ubiquitous language.
- Prefer small, focused methods, descriptive intermediate variables, and straightforward control flow.
- Avoid extensive comments and narrative explanations of what the code does. Improve names and structure first; keep necessary comments concise and focused on non-obvious reasons, constraints, or trade-offs.
- Avoid obscure PHP-specific tricks and nested callback pipelines such as combinations of `array_map` and `array_walk`. Prefer explicit iteration or clearly named methods when they make intent easier to follow.
- Target the project's PHP 8.5 runtime. Keep tooling and dependencies compatible with it, and verify installed versions before relying on language or package features. Review compatibility when upgrading to a newer stable release.
- Use modern PHP features when they improve clarity, type safety, and correctness.
- Follow applicable PHP-FIG PSR standards for autoloading, coding style, and interoperability. Use standard PSR contracts where relevant and enforce the project's coding style through its configured formatter.
- Apply the strictest practical typing: declare `strict_types=1` in application-owned PHP files and explicitly type parameters, return values, and properties, subject to inherited framework contracts.
- Prefer precise domain types, value objects, enums, and typed DTOs over primitive ambiguity, unstructured arrays, and `mixed`. Use concise PHPDoc array shapes and collection element types where native PHP types cannot express the contract.
- Validate and convert external input at system boundaries; strict typing does not replace runtime validation of untrusted data.
- Name application-owned interfaces with the `Interface` suffix, such as `ExecutionRepositoryInterface`, and data transfer objects with the exact `Dto` suffix, such as `ExecutionResultDto`.
- Apply fail-fast behavior: reject invalid input, unsupported operations, and violated preconditions as soon as they are detected, before producing side effects. Never silently continue with invalid state or swallow failures.

## Framework independence

- Keep the domain and application layers framework-agnostic. Business rules and use-case coordination must not depend on Laravel, Eloquent, facades, HTTP requests, or queue implementations.
- Use plain PHP objects and explicit ports for dependencies that cross these boundaries.
- Keep framework integration, dependency injection configuration, persistence, and transport concerns in adapters and the composition root.
- Framework independence applies to the core; adapters should use the framework's native capabilities and conventions.

## Domain-driven design

Follow [ddd.md](ddd.md) for modeling terminology, aggregate design, and the placement of business behavior. It uses Vaughn Vernon's interpretation of DDD and DDD Crew's collaborative modeling tools.

Keep business rules in the domain, use the application layer to coordinate use cases, and enforce the framework and persistence boundaries defined below. The ADRs record our local implementation compromises.

## Money and exact arithmetic

- Never use `float` for monetary values or financial calculations. This includes intermediate calculations, comparisons, persistence, and serialization.
- Use appropriate Money and exact-arithmetic libraries for monetary modeling and calculations. Select compatible libraries and document the decision in an ADR; do not implement ad hoc floating-point fixes.
- Model money as an amount together with its currency. Define precision, scale, rounding mode, and rounding boundaries explicitly for each operation, including allocation and currency conversion.
- Preserve exact values across boundaries using decimal strings or integer minor units where appropriate. Use exact database types, and never convert through `float` when reading, writing, or formatting values.

## Ports and adapters

- Use hexagonal architecture (ports and adapters).
- Define ports at the application or domain boundary for interactions with external systems.
- Implement those ports through infrastructure adapters, including persistence and integrations with the analytics application.
- Keep HTTP controllers, console commands, and message consumers as inbound adapters that invoke application use cases.
- Keep controllers thin: accept and validate transport input, translate it into typed application input, invoke a use case, and map its result to a response. Business rules, persistence operations, and orchestration belong behind the application boundary.
- Direct dependencies inward: adapters depend on application and domain contracts; the domain does not depend on adapters.

## Directory structure and layers

- Organize source code into four architectural layers: `Infrastructure`, `Ui`, `Application`, and `Domain`. Directory names and grouping must show each class's responsibility.
- Keep domain models, invariants, domain services, and domain events in `Domain`.
- Keep use cases, command and query handlers, orchestrators, and application ports in `Application`.
- Keep user-interface controllers, interactive components, and presentation logic in `Ui`, acting as inbound adapters. This layer covers the GUI, HTTP APIs, and console entry points.
- Keep persistence, messaging, external integrations, and framework wiring in `Infrastructure`.
- Preserve this layer separation within bounded contexts where the code is grouped by context. Directory placement and namespaces must make each class's architectural responsibility clear.
- Dependencies must point inward: `Ui` and `Infrastructure` may depend on `Application` and `Domain`; `Application` may depend on `Domain`; `Domain` must not depend on the other layers. `Ui` must not depend directly on `Infrastructure`. Compose adapters through the container. Framework wiring in the composition root may reference concrete adapters to register them; ordinary adapter code must not use this exception to bypass application ports.
- Enforce layer boundaries and allowed dependency directions automatically with Deptrac. Its rules must cover all application-owned source code and prevent framework or infrastructure dependencies from leaking into the core.
- Run Deptrac as a required CI check and fail the build on architectural violations. Keep its configuration aligned with layer and bounded-context boundaries as the code evolves.
- Resolve forbidden dependencies. Any necessary exclusion or baseline entry must be narrowly scoped, justified, and recorded in an ADR.

Use the following target layout. Names in braces are placeholders; create optional elements only when a use case requires them. These directories organize code, not transaction boundaries.

```text
src/{BoundedContext}/
├── Application/
│   ├── {Aggregate}/
│   │   ├── Command/
│   │   │   └── {Action}/
│   │   │       ├── {Action}Command.php
│   │   │       └── {Action}CommandHandler.php
│   │   └── Query/
│   │       ├── {Aggregate}QueryInterface.php
│   │       └── Dto/
│   │           └── {Aggregate}Dto.php
│   ├── IntegrationEvent/
│   │   └── {Aggregate}{PastTenseVerb}IntegrationEvent.php
│   ├── IntegrationEventSubscriber/
│   │   └── {Event}Subscriber.php
│   ├── Port/
│   │   ├── TransactionManagerInterface.php
│   │   ├── DomainEventBusInterface.php
│   │   ├── IntegrationEventPublisherInterface.php
│   │   └── AuditTrailInterface.php
│   └── Workflow/
│       └── {Process}/
│           ├── {Process}Orchestrator.php
│           └── {Process}StoreInterface.php
├── Domain/
│   └── {Aggregate}/
│       ├── {Aggregate}.php
│       ├── Dto/
│       │   └── {Aggregate}StateDto.php
│       ├── Entity/
│       │   └── {ChildEntity}.php
│       ├── ValueObject/
│       │   └── {Aggregate}Id.php
│       ├── Enum/
│       │   └── {Concept}.php
│       ├── Event/
│       │   └── {Aggregate}{PastTenseVerb}.php
│       ├── Exception/
│       │   └── {Rule}Exception.php
│       ├── Factory/
│       │   ├── {Aggregate}FactoryInterface.php
│       │   └── {Aggregate}Factory.php
│       └── Repository/
│           ├── {Aggregate}RepositoryInterface.php
│           └── Exception/
│               └── {Aggregate}DoesNotExistException.php
├── Infrastructure/
│   ├── {Aggregate}/
│   │   ├── {Aggregate}Query.php
│   │   ├── {Aggregate}Repository.php
│   │   └── Eloquent/
│   │       ├── {Aggregate}Model.php
│   │       └── {ChildEntity}Model.php
│   ├── Persistence/
│   │   └── LaravelTransactionManager.php
│   ├── Audit/
│   │   └── PostgresAuditTrail.php
│   ├── Messaging/
│   │   ├── LaravelDomainEventBus.php
│   │   ├── Inbox/
│   │   ├── Outbox/
│   │   └── Jobs/
│   ├── Workflow/
│   │   └── {Process}Store.php
│   ├── Providers/
│   │   └── {BoundedContext}ServiceProvider.php
│   └── Resources/
│       ├── config.php
│       └── Migrations/
│           └── {yyyy_mm_dd_hhmmss}_create_{context}_{table}_table.php
└── Ui/
    ├── Http/
    │   ├── Api/
    │   │   └── {Aggregate}Controller.php
    │   ├── Web/
    │   │   └── {Aggregate}Controller.php
    │   ├── Request/
    │   │   └── {Action}Request.php
    │   └── routes.php
    ├── Input/
    │   └── {Action}InputDto.php
    ├── Livewire/
    │   └── {Component}.php
    └── ConsoleCommands/
        └── {Action}{Aggregate}Command.php
```

Application commands and domain factories are plain PHP objects. Console commands, queued jobs, HTTP requests, and Eloquent factories are separate framework adapters. Persisted workflow state has its own model and port when needed; the orchestrator is not itself an aggregate.

### Laravel integration

- Add the PSR-4 mapping `"TradeManeuver\\": "src/"` alongside the existing `App\\` mapping, then regenerate Composer autoload files. A context class consequently uses a namespace such as `TradeManeuver\Execution\Domain\Order`. Laravel permits this organization when classes are autoloadable; see its [directory structure documentation](https://laravel.com/framework/docs/13.x/structure#introduction).
- Keep the standard root `bootstrap/`, `config/`, `routes/`, `resources/`, `database/`, `storage/`, and `tests/` directories. Context migrations may live under the context, while framework-owned migrations remain under `database/migrations`. Keep Blade templates under `resources/views/{context}/`, and tests under `tests/Unit/{BoundedContext}/` and `tests/Feature/{BoundedContext}/`.
- Register each context provider in `bootstrap/providers.php`. Bind interfaces and configure plain PHP handlers in `register()`; register routes, migrations, commands, and listeners in `boot()`. Framework wiring may reference the context's UI classes for registration. This uses Laravel's [service provider lifecycle](https://laravel.com/framework/docs/13.x/providers).
- Merge context configuration from a PHP array with `mergeConfigFrom()` in `register()`. In `boot()`, load HTTP routes with `loadRoutesFrom()`, migration directories with `loadMigrationsFrom()`, and console command classes with the provider's `commands()` method. These methods are available in the installed Laravel 13.38.0. Preserve route middleware, authentication, authorization, API versioning, and configuration/route cache compatibility when registering context resources.
- Use native Laravel migrations with timestamped filenames and `up()`/`down()` methods, not Doctrine-style `Version{timestamp}.php` files. Migration filenames must be globally unique across contexts; use a context-specific table description. Generate migrations with Artisan's `--path` option. Use `config.php` rather than a YAML loader.
- Register custom Livewire components explicitly when Livewire is adopted, using the installed version's supported registration mechanism. Keep Laravel queue contracts and serialization concerns in `Infrastructure/Messaging/Jobs`; jobs invoke plain application handlers rather than making application commands framework jobs.
- Configure Deptrac for `src/`, the four layers, and individual bounded contexts. Include application-owned code remaining under the existing root namespace. Permit imports of another context's designated public integration contracts only; keep its domain internals and tables private. Allow framework dependencies in adapters and framework bootstrap code, with a separate rule for composition-root registration. Deptrac is not yet installed or configured.

## GUI approach

- Build the GUI with solutions analogous to Symfony UX, such as Livewire in the Laravel ecosystem, using server-rendered views and interactive components.
- Keep GUI components focused on presentation, user interaction, and invoking application use cases. Business rules and workflow coordination remain in the domain and application layers.
- Send state-changing actions through application command handlers and retrieve display data through the simplified read side, without a query bus.
- Keep GUI framework dependencies in the presentation layer so that domain and application code remain framework-agnostic.

## REST API approach

- When building a complex REST API, consider API Platform and assess its fit with the project's requirements, installed package versions, and architectural boundaries.
- Keep API framework concerns in adapters and route operations through application use cases and the simplified read side. Record the adoption decision and its trade-offs in an ADR.

## Security by design

- Design and implement the architecture to resist the risks covered by the current [OWASP Top 10](https://top10.owasp.org/) across GUI, APIs, message consumers, integrations, and deployment configuration.
- Define trust boundaries explicitly, validate untrusted input, and enforce authentication and authorization for every protected operation, including background execution. Apply least privilege and deny access by default.
- Use parameterized database access, context-appropriate output escaping, CSRF protection for browser actions, secure secret management, and appropriate cryptographic controls.
- Address insecure design, unsafe configuration, dependency and supply-chain risks, and software and data integrity throughout development and deployment.
- Handle exceptional conditions safely, avoid exposing sensitive information in errors or logs, and provide actionable security logging and alerting.
- Review security-sensitive design decisions and verify relevant controls through focused tests and security checks as part of delivery.

## Logging and auditability

Keep enough durable history to reconstruct each execution instruction's processing, state transitions, outcomes, and failures.

- Produce structured logs for workflow progress, significant decisions, external interactions, retries, concurrency conflicts, and errors. Use consistent severity levels and include actionable failure context.
- Propagate correlation and causation identifiers across requests, commands, domain events, queued jobs, and external calls. Include instruction, execution, and idempotency identifiers where relevant so that one operation can be traced end to end.
- Record errors with their cause and relevant exception context, and distinguish expected domain rejections from infrastructure failures. Surface failures to the responsible caller or orchestrator and avoid redundant logging of the same exception at every layer.
- Keep a durable audit trail separate from diagnostic log streams. Record who or what initiated an action, when it occurred, the affected entity, the state transition, and the outcome, including retries and duplicate handling where relevant.
- Make audit records append-only and protect them against unauthorized modification or deletion. Commit audit records atomically with local business changes. If audit records are delivered to a separate system, commit publication intent through an outbox and require durable acceptance and recovery under the receiver contract.
- Define retention and access policies for logs and audit records. Redact credentials, tokens, and sensitive payload fields while retaining enough context to investigate incidents and reconcile execution outcomes.

## Architecture decision records

- Record detailed architectural decisions as Architecture Decision Records (ADRs) in `docs/ADR`, in English.
- Give each ADR a sequential identifier and descriptive filename, such as `docs/ADR/adr-0001-domain-independence-from-eloquent.md`.
- Describe the context, decision, status, alternatives considered, and consequences, including relevant trade-offs, security concerns, and operational effects.
- Link related and superseding ADRs. Preserve the history of accepted decisions and mark superseded records explicitly rather than silently rewriting them.

## Persistence and Eloquent

The decision to separate domain aggregates from Eloquent persistence models, including the cost of manual mapping, is explained in [ADR-0001](ADR/adr-0001-domain-independence-from-eloquent.md).

Expose `id()` and one immutable `state(): {Aggregate}StateDto` contract for repository mapping, as defined in [ADR-0002](ADR/adr-0002-aggregate-state-for-eloquent-persistence.md). This exposes persisted domain state while keeping mutations inside aggregate behavior. State DTOs are internal domain contracts, separate from query DTOs and integration events.

- Use Eloquent's native Active Record pattern for persistence models.
- Keep Eloquent models and their persistence behavior in infrastructure adapters, behind the core's ports. Domain objects and application contracts must not expose Eloquent models, builders, or collections.
- Use Eloquent relationships, scopes, casts, and transactions where appropriate within those adapters.
- Do not replace Eloquent with Doctrine or introduce a custom Data Mapper architecture to enforce framework independence.
- Reconstitute domain aggregates and persist their state through repository code using native Eloquent models. Persist root and child changes consistently. Reconstitution validates the stored state without replaying commands, rechecking historical actions against current eligibility policies, or emitting new domain events. Changes that make stored state incompatible require an explicit migration or compatibility policy.
- Expose write access through aggregate-root repositories, not repositories for each child entity. Do not let controllers, query adapters, model observers, relationship cascades, or bulk updates bypass aggregate invariants or concurrency protection. Eloquent lifecycle events are not substitutes for domain events.

## CQRS

- Separate commands that change state from queries that retrieve data.
- Execute commands through application command handlers and the domain model so that all domain invariants remain enforced.
- Keep the read side simple: invoke query services or query handlers directly, without a query bus.
- Let read implementations retrieve projections or read models through infrastructure adapters without reconstructing aggregates solely for display.
- Queries must not change business state. Separate read and write models do not require separate databases.
- Read DTOs and projections may combine data within a context without loading aggregates, but may be stale. Never use a projection as the authoritative basis for a write-side invariant; reload and validate the relevant aggregate within the command's consistency boundary.

## Aggregate and transaction boundaries

Following our preference for Vaughn Vernon's approach, model actual invariants, keep aggregates small, reference other aggregates by identity, and use eventual consistency between independent aggregates. The directory layout supports these rules but cannot prove that the business model follows them.

- A state-changing command should modify one business aggregate instance per transaction, including its internal entities and value objects. A bounded context or aggregate directory is not a transaction boundary by itself. Revisit the model when a genuine immediate invariant appears to span aggregates; document any justified exception in an ADR.
- Application handlers define the use-case transaction boundary through `TransactionManagerInterface`; the infrastructure implementation uses a specific Laravel PostgreSQL connection's `transaction()` method. Repositories participate in that transaction and must not independently commit partial changes.
- Commit aggregate changes, the command/inbox processing outcome, audit records, and outbox entries on the same database connection in one transaction. These supporting records do not represent additional business aggregates. An exception must roll back all of them; using the same PostgreSQL server through different connections is not sufficient for atomicity.
- After rollback, record terminal rejections or failed attempts in a separate transaction where an audit record is required. Persist a terminal rejection together with its deduplication outcome so that a duplicate receives the same result. Do not report rolled-back business changes as committed or mark a retryable failure as successfully processed.
- Keep references to other aggregates as typed identifiers. Do not traverse Eloquent relationships to mutate a different aggregate in the same operation. Cross-context interactions use explicit application/integration contracts.
- Orchestrators coordinate separate commands and transactions. Persist workflow progress in its own consistency boundary and exchange durable commands and outcomes; do not wrap the orchestrator and all participating aggregates in one outer transaction. Synchronous event handlers must not silently expand a command's write boundary.
- Separate remote execution from local database transactions. Commit intent, invoke the external system with a stable idempotency key, and record the confirmed or uncertain result in a later transaction. Provide reconciliation and compensation; a local rollback cannot undo a remote action.

For example, if two instructions each reserve 70 units from 100 available, both may pass a preliminary read. The authoritative reservation must enforce the available-quantity invariant in one protected aggregate boundary. Once one reservation commits, the competing attempt must reload state and reject the reservation that would exceed the limit. Eventual consistency between separate instructions cannot by itself enforce that limit.

## Domain events and integration events

Separate internal business facts from public integration contracts. This lets the domain model evolve while keeping communication with consumers, including the analytics application, stable.

### Domain events

- Define immutable, typed events in `Domain/{Aggregate}/Event`, with names describing completed business facts and payloads that may contain domain value objects. Keep them independent of Laravel and transport mechanisms.
- Record events during aggregate behavior. Where local reactions are needed, dispatch them synchronously within the command's transaction through `DomainEventBusInterface`, implemented by an infrastructure adapter.
- Local application handlers may translate events into integration contracts or write transactional audit records. They must not modify other aggregates, perform remote calls, or use queued listeners. A handler failure before commit rolls back the local transaction.
- Domain events are internal, in-process contracts. Dispatching them does not itself provide durable delivery or prove that the enclosing transaction committed.

### Integration events

- Define immutable, versioned public contracts in `Application/IntegrationEvent`. Use explicit serializable payloads with stable event identities, occurrence timestamps, correlation and causation identifiers, and relevant business data. Do not serialize domain objects or Eloquent models as public contracts.
- Translate selected domain events in the application layer. Not every domain event needs an integration counterpart; choose the information consumers actually require.
- Publish through `IntegrationEventPublisherInterface`. Its infrastructure implementation records publication intent in the transactional outbox on the same connection and transaction as the aggregate change; it does not invoke remote consumers inline.
- Use the PostgreSQL-backed queue to dispatch committed outgoing messages and process incoming messages locally. External delivery uses transport adapters and the receiver's public contract; services must not share queue tables. Keep subscribers in `Application/IntegrationEventSubscriber` framework-independent; infrastructure jobs adapt delivery to those subscribers.
- Preserve explicit orchestration: subscribers invoke defined use cases or orchestrators in separate transactions. Listener chains must not implicitly define business workflows. Delivery, duplicate handling, and receiver obligations follow the reliable messaging rules below.

## PostgreSQL-backed queues

- Use PostgreSQL as the backing store for asynchronous job and message queues.
- Keep enqueueing, reservation, acknowledgement, retry, and failed-job handling in infrastructure adapters; the core must remain independent of queue implementation details.
- Run a recoverable dispatcher for committed outbox entries, preserving message identities across enqueue attempts. Workers must process committed state and safely handle retries and concurrent delivery under the reliable messaging rules below.
- Decide enqueue transaction behavior explicitly. In the current skeleton the database queue has `after_commit=false`; an insertion on the same database connection inside the business transaction can be atomic with it, whereas a different connection is not covered. Laravel's `after_commit=true` or `afterCommit()` defers dispatch, but a process crash after commit can still prevent dispatch. Required publication therefore uses the durable outbox and a recoverable dispatcher, not only an in-memory after-commit callback. See [jobs and database transactions](https://laravel.com/framework/docs/13.x/queues#jobs-and-database-transactions).

## Idempotency and reliable messaging

Every retryable operation, including execution instructions, must prevent unintended repeated business effects after duplicate delivery, timeouts, or process restarts.

The Outbox durably records publication intent and supports retries on the sender side. End-to-end reliable processing also depends on the receiver's durable acceptance, acknowledgement semantics, idempotency, and failure recovery. The Outbox alone guarantees neither successful processing nor exactly-once business effects.

- Define the idempotency boundary, key, and retention policy for each retryable operation. Persist processing state and outcomes durably, and enforce key uniqueness atomically.
- Distinguish retries from new instructions. Reuse the recorded outcome for completed duplicates and reject reuse of a key with a different payload.
- Where we control the receiver, use the Inbox pattern when durable deduplication is required. Commit the inbox processing outcome and local business changes in the same transaction. If intake and execution are separate, acknowledge acceptance only after durably storing the message and responsibility for processing it; acknowledge completion only after committing the processing outcome. A pending inbox entry must remain eligible for recovery.
- Scope inbox uniqueness by consumer and message identity, and instruction deduplication by the agreed business scope and idempotency key. Enforce both in PostgreSQL; concurrent duplicate checks must not rely on a prior lookup alone.
- Use the transactional Outbox pattern whenever a local state change must reliably produce an outgoing message. Commit the state change and outbox entry in the same transaction, then publish through a retryable dispatcher.
- Design for at-least-once delivery, duplicate publication, and out-of-order arrival. Define retry and retention policies, terminal failure handling, and operational recovery; retries alone do not guarantee eventual success when a receiver remains unavailable or rejects a message. Post-commit delivery failures require recovery or explicit compensation and do not roll back the original committed business change.
- For external receivers, document and verify their acceptance and processing guarantees. Distinguish sender publication, durable receiver acceptance, and business completion. An acknowledgement of acceptance must not be treated as completion; require an explicit result or status contract for completion.
- Propagate stable idempotency keys to external execution systems where supported. When an external outcome is uncertain, reconcile its status before retrying an irreversible action. If the receiver provides neither deduplication nor a reliable way to resolve that outcome, stop automatic retries and require explicit recovery.
- Verify duplicate delivery, retries after partial failure, and concurrent execution in tests.

## Concurrency control

Choose optimistic or pessimistic locking for each invariant according to contention, consistency requirements, and the consequences of conflicting operations.

- Use optimistic locking when conflicts are infrequent: compare an expected version atomically during the write, detect conflicts, and reject or retry only after reloading state and re-evaluating the operation.
- Use pessimistic locking when operations must serialize access to contested state. Acquire the required database locks within a transaction, keep transactions short, and use a consistent lock order.
- Enforce applicable uniqueness and integrity constraints in the database as a final safeguard. An application-level check followed by an unprotected write is insufficient.
- Handle deadlocks, lock timeouts, and version conflicts explicitly. Bound retries and preserve idempotency across every attempt.
- Do not rely on process-local locks for correctness across workers or service instances, and do not hold database locks while waiting for remote calls.
- Eloquent `save()` does not automatically implement optimistic version checks in the installed framework. For optimistic locking, perform an atomic update conditioned on aggregate identity and the loaded version, increment the version, and require exactly one affected row. A zero-row update is a concurrency conflict, not success; `updated_at` alone is not a version contract.
- Load a consistent root-and-child snapshot before evaluating domain rules. At PostgreSQL's default Read Committed isolation, separate reads can see different committed versions. For optimistic loading, capture the root version before reading children, read it again after loading, and retry the load if it changed; carry the original expected version through the conditional write. A suitable transaction snapshot is another option. Repositories must load the state needed to evaluate invariants; domain behavior must not trigger Eloquent lazy loading or use stale cached snapshots as authoritative state.
- Advance or lock the aggregate root for every write that can affect its invariants, including child-only updates and inserts. Persist root and child changes in one transaction, and roll back child changes and outbox records if the version check fails. For pessimistic locking, acquire the root lock before loading the state used to make the decision; every writer must follow the same protocol.
- PostgreSQL isolation and row locks do not automatically protect predicates or rows that do not yet exist. For competing inserts and limits across rows, choose an appropriate unique/exclusion constraint, lock a stable owning aggregate row, use an atomic conditional update, or select serializable isolation with whole-transaction retries. Record the choice for the invariant; see PostgreSQL's [isolation](https://www.postgresql.org/docs/18/transaction-iso.html) and [locking](https://www.postgresql.org/docs/18/explicit-locking.html) documentation.
- Laravel can retry transaction callbacks for supported database concurrency errors. Reload aggregates and recreate event buffers on every attempt; never reuse already-mutated objects or perform remote effects inside a retried callback. Handle application version conflicts explicitly rather than assuming Laravel retries them. Keep retries bounded; see [transaction retries](https://laravel.com/framework/docs/13.x/database#handling-deadlocks).

### Required consistency verification

The test pyramid, PHPUnit coverage, Behat/Gherkin integration flows, mutation testing, and planned contract/E2E coverage are defined in [testing-strategy.md](testing-strategy.md).

Before delivering a write use case, verify the following for its actual invariants:

- Aggregate behavior rejects invalid transitions and preserves invariants, including reconstitution and value-object validation.
- PostgreSQL integration tests with independent connections and controlled interleavings cover inconsistent root-and-child reads, stale versions, child-only changes, contested inserts, pessimistic locks, and applicable deadlock/serialization retries.
- Failure tests prove aggregate, inbox, audit, and outbox writes roll back together; concurrent duplicate instructions produce one business effect.
- Recovery tests cover a crash after commit before publication, duplicate publication, out-of-order messages, receiver failure before acknowledgement, lost acknowledgements, acceptance without business completion, workflow restart, and uncertain external execution outcomes.
- Deptrac rejects forbidden layer and context dependencies, and adapter registration works with cached routes and configuration.

The existing Eloquent CRUD test verifies basic database integration. Aggregate and concurrency guarantees still need use-case implementations and tests.

## Explicit orchestration

- Coordinate multi-step business workflows through explicitly defined application orchestrators or process managers.
- Use orchestration as the default approach for multi-step business workflows. Keep workflow order, state transitions, retries, timeouts, and compensation visible in the orchestrator.
- Keep business invariants in the domain model; orchestrators coordinate participants through ports and application use cases.
- Persist workflow progress when execution spans messages, external calls, or process restarts. Make each step and recovery action idempotent.
- Use events to communicate facts and outcomes without turning implicit listener chains into the workflow's control flow.

## Twelve-Factor App

Apply the twelve factors to deployment and operations:

1. Codebase: Maintain one codebase in version control, with multiple deployments.
2. Dependencies: Explicitly declare and isolate dependencies.
3. Config: Store deployment-specific configuration in the environment.
4. Backing services: Treat databases, queues, and other backing services as attached resources.
5. Build, release, run: Keep build, release, and runtime stages separate.
6. Processes: Run stateless processes and store durable state in backing services.
7. Port binding: Expose HTTP through a bound port with explicitly declared server dependencies. The local Compose environment uses Nginx and PHP-FPM; production must define its own complete serving stack.
8. Concurrency: Scale web and worker processes independently.
9. Disposability: Support fast startup and graceful shutdown, including safe handling of interrupted work.
10. Dev/prod parity: Keep development, staging, and production environments as similar as possible.
11. Logs: Treat logs as event streams and let the runtime environment collect and route them.
12. Admin processes: Run administrative and maintenance tasks as one-off processes using the same release and configuration as the service.

## Microservice boundaries and integration

- Keep the service independently deployable, with a focused execution responsibility and ownership of its data.
- Integrate with the analytics application through explicit, versioned contracts and ports; do not share databases or internal domain models.
- Keep analytical decision-making in the analytics application while retaining responsibility for execution validity and domain invariants in this service.
- Make execution instructions traceable and handle duplicate delivery and retries safely to prevent unintended repeated execution.
- Expose execution status, results, and failures through defined integration contracts.
