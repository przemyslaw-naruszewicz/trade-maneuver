# Architecture

The repository contains a Laravel skeleton with PostgreSQL integration. Bounded contexts, the domain model, and Inbox/Outbox are target requirements and still need implementation. Deptrac is installed and runs in CI with one placeholder layer and an empty ruleset; architectural boundary rules are pending. Accepted ADRs record decisions about the target, independently of implementation status.

## Service purpose

The execution service will receive execution instructions from an analytics application, validate them against its domain rules, coordinate execution, and expose status and results. The analytics application owns analytical decision-making.

## Object-oriented programming

- Use object-oriented programming throughout the application. Give each object clear responsibilities and encapsulate its behavior and state. Allow state changes only through domain behavior that protects invariants.
- Prefer composition, delegation, and explicit dependencies. Use inheritance only for substitutable types or framework contracts, such as Eloquent models, rather than for code reuse alone.
- Follow SOLID: the Single Responsibility, Open/Closed, Liskov Substitution, Interface Segregation, and Dependency Inversion principles.
- Use design patterns and abstractions when they solve a specific domain or technical problem.

## Code semantics, readability, and PHP

- Use the ubiquitous language in names, types, and control flow. Keep methods focused, use descriptive intermediate variables, and make control flow straightforward.
- Explain code through its names and structure. Reserve comments for reasons, constraints, or trade-offs that the code cannot make clear.
- Avoid obscure PHP-specific tricks and nested callback pipelines such as combinations of `array_map` and `array_walk`. Prefer explicit iteration or clearly named methods when they make intent easier to follow.
- Target the project's PHP 8.5 runtime. Keep tooling and dependencies compatible with it, and verify installed versions before relying on language or package features. Review compatibility when upgrading to a newer stable release.
- Use modern PHP features where they make code clearer or improve type safety and correctness.
- Follow applicable PHP-FIG PSR standards for autoloading, coding style, and interoperability. Use standard PSR contracts where relevant and enforce the project's coding style through its configured formatter.
- Apply the strictest practical typing: declare `strict_types=1` in application-owned PHP files and explicitly type parameters, return values, and properties, subject to inherited framework contracts.
- Use domain types, value objects, enums, and typed DTOs to avoid ambiguous primitives, unstructured arrays, and `mixed`. Add concise PHPDoc array shapes and collection element types where native PHP types cannot express the contract.
- Validate and convert external input at system boundaries; strict typing does not replace runtime validation of untrusted data.
- Name application-owned interfaces with the `Interface` suffix, such as `ExecutionRepositoryInterface`, and data transfer objects with the exact `Dto` suffix, such as `ExecutionResultDto`.
- Reject invalid input, unsupported operations, and violated preconditions before producing side effects. Report failures as soon as they are detected; never swallow them or continue with invalid state.

## Framework independence

- Keep the domain and application layers framework-agnostic. Business rules and use-case coordination must not depend on Laravel, Eloquent, facades, HTTP requests, or queue implementations.
- Use plain PHP objects in the core and explicit ports for dependencies that cross its boundary. Put framework integration, dependency injection configuration, persistence, and transport in adapters and the composition root. Adapters should use the framework's native capabilities and conventions.

## Domain-driven design

Follow the [DDD rules](ddd.md) for modeling terminology, aggregate design, and the placement of business behavior. They use Vaughn Vernon's interpretation of DDD and DDD Crew's collaborative modeling tools. Use the [domain dictionary](dictionary.md) for business terminology in code, tests, APIs, events, and documentation. ADRs record local implementation compromises.

## Money and exact arithmetic

- Never use `float` for monetary values or financial calculations. This includes intermediate calculations, comparisons, persistence, and serialization.
- Use appropriate Money and exact-arithmetic libraries for monetary modeling and calculations. Select compatible libraries and document the decision in an ADR; do not implement ad hoc floating-point fixes.
- Model money as an amount together with its currency. Define precision, scale, rounding mode, and rounding boundaries explicitly for each operation, including allocation and currency conversion.
- Preserve exact values across boundaries using decimal strings or integer minor units where appropriate. Use exact database types, with no conversion through `float` when reading, writing, or formatting values.

## Ports and adapters

- Use hexagonal architecture (ports and adapters).
- Define ports at the application or domain boundary for interactions with external systems.
- Implement those ports through infrastructure adapters, including persistence and integrations with the analytics application.
- Keep HTTP controllers, console commands, and message consumers as inbound adapters that invoke application use cases.
- Keep controllers thin: accept and validate transport input, translate it into typed application input, invoke a use case, and map its result to a response. Business rules, persistence operations, and orchestration belong behind the application boundary.

## Directory structure and layers

- Organize source code into four architectural layers: `Infrastructure`, `Ui`, `Application`, and `Domain`. Directory names and grouping must show each class's responsibility.
- Keep domain models, invariants, domain services, and domain events in `Domain`.
- Keep use cases, command and query handlers, orchestrators, and application ports in `Application`.
- Keep user-interface controllers, interactive components, and presentation logic in `Ui`, acting as inbound adapters. This layer covers the GUI, HTTP APIs, and console entry points.
- Keep persistence, messaging, external integrations, and framework wiring in `Infrastructure`.
- Apply the same layer separation within each bounded context. Directory placement and namespaces must identify each class's responsibility.
- Dependencies must point inward: `Ui` and `Infrastructure` may depend on `Application` and `Domain`; `Application` may depend on `Domain`; `Domain` must not depend on the other layers. `Ui` must not depend directly on `Infrastructure`. Compose adapters through the container. Framework wiring in the composition root may reference concrete adapters to register them; ordinary adapter code must not use this exception to bypass application ports.
- Enforce layer boundaries and dependency directions with Deptrac across all application-owned source code. Its rules must keep framework and infrastructure dependencies out of the core and cover bounded-context boundaries. Require the check in CI and fail the build on violations. Update the configuration as the code evolves.
- Resolve forbidden dependencies. Any necessary exclusion or baseline entry must be narrowly scoped, justified, and recorded in an ADR.

Use the following target layout. Names in braces are placeholders; create optional elements only when a use case requires them. Transaction boundaries follow aggregates and use cases independently of this layout.

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

Application commands and domain factories are plain PHP objects. Console commands, queued jobs, HTTP requests, and Eloquent factories are framework adapters. Store persisted workflow state through its own model and port when needed; keep it separate from the orchestrator, which is not an aggregate.

### Laravel integration

- Add the PSR-4 mapping `"TradeManeuver\\": "src/"` alongside the existing `App\\` mapping, then regenerate Composer autoload files. Context classes use namespaces such as `TradeManeuver\Execution\Domain\Order`. Laravel supports this organization through autoloading; see its [directory structure documentation](https://laravel.com/framework/docs/13.x/structure#introduction).
- Keep the standard root `bootstrap/`, `config/`, `routes/`, `resources/`, `database/`, `storage/`, and `tests/` directories. Context migrations may live under the context, while framework-owned migrations remain under `database/migrations`. Keep Blade templates under `resources/views/{context}/`, and tests under `tests/Unit/{BoundedContext}/` and `tests/Feature/{BoundedContext}/`.
- Register each context provider in `bootstrap/providers.php`. Bind interfaces and configure plain PHP handlers in `register()`; register routes, migrations, commands, and listeners in `boot()`. Framework wiring may reference the context's UI classes for registration. This uses Laravel's [service provider lifecycle](https://laravel.com/framework/docs/13.x/providers).
- Merge context configuration from a PHP array with `mergeConfigFrom()` in `register()`. In `boot()`, load HTTP routes with `loadRoutesFrom()`, migration directories with `loadMigrationsFrom()`, and console command classes with the provider's `commands()` method. These methods are available in the installed Laravel 13.38.0. Preserve route middleware, authentication, authorization, API versioning, and configuration/route cache compatibility when registering context resources.
- Use native Laravel migrations with timestamped filenames and `up()`/`down()` methods instead of Doctrine-style `Version{timestamp}.php` files. Make migration filenames globally unique across contexts with a context-specific table description, and generate them with Artisan's `--path` option. Store configuration in `config.php`; do not introduce a YAML loader.
- Register custom Livewire components explicitly when Livewire is adopted, using the installed version's supported registration mechanism. Keep Laravel queue contracts and serialization in `Infrastructure/Messaging/Jobs`. Jobs invoke plain application handlers; application commands remain independent of framework job contracts.
- Configure Deptrac for `src/`, the four layers, and individual bounded contexts. Include application-owned code remaining under the existing root namespace. Permit imports of another context's designated public integration contracts only; keep its domain internals and tables private. Allow framework dependencies in adapters and framework bootstrap code, with a separate rule for composition-root registration. The current `deptrac.yaml` defines source paths, one placeholder layer, and an empty ruleset; these boundary rules remain pending.

## GUI approach

- Use server-rendered views and interactive components for the GUI, with Laravel tools such as Livewire analogous to Symfony UX.
- Keep GUI components focused on presentation, user interaction, and invoking application use cases. Business rules belong in the domain; the application layer coordinates workflows.
- Send state-changing actions through application command handlers and retrieve display data through the simplified read side, without a query bus.

## REST API approach

- For a complex REST API, assess API Platform against the project's requirements, installed package versions, and architectural boundaries. Record an adoption decision and its trade-offs in an ADR.
- Keep API framework code in adapters. Route operations through application use cases and the simplified read side.

## Security by design

- Design and implement the architecture to resist the risks covered by the current [OWASP Top 10](https://top10.owasp.org/) across GUI, APIs, message consumers, integrations, and deployment configuration.
- Define trust boundaries explicitly, validate untrusted input, and enforce authentication and authorization for every protected operation, including background execution. Apply least privilege and deny access by default.
- Use parameterized database access, context-appropriate output escaping, CSRF protection for browser actions, secure secret management, and appropriate cryptographic controls.
- Address insecure design, unsafe configuration, dependency and supply-chain risks, and software and data integrity throughout development and deployment.
- Handle exceptional conditions safely, avoid exposing sensitive information in errors or logs, and provide actionable security logging and alerting.
- Review security-sensitive design decisions and verify relevant controls through focused tests and security checks as part of delivery.

## Logging and auditability

Keep a durable history of each execution instruction's processing, state transitions, outcomes, and failures.

- Produce structured logs for workflow progress, significant decisions, external interactions, retries, concurrency conflicts, and errors. Use consistent severity levels and include actionable failure context.
- Propagate correlation and causation identifiers across requests, commands, domain events, queued jobs, and external calls. Include instruction, execution, and idempotency identifiers where relevant so that one operation can be traced end to end.
- Record errors with their cause and exception context. Distinguish expected business rejections from infrastructure failures, report failures to the responsible caller or orchestrator, and avoid logging the same exception at every layer.
- Keep a durable audit trail separate from diagnostic log streams. Record who or what initiated an action, when it occurred, the affected entity, the state transition, and the outcome, including retries and duplicate handling where relevant.
- Make audit records append-only and protect them against unauthorized modification or deletion. Commit audit records atomically with local business changes. If audit records are delivered to a separate system, commit publication intent through an outbox and require durable acceptance and recovery under the receiver contract.
- Define retention and access policies for logs and audit records. Redact credentials, tokens, and sensitive payload fields while retaining enough context to investigate incidents and reconcile execution outcomes.

## Architecture decision records

- Record detailed architectural decisions as Architecture Decision Records (ADRs) in `docs/ADR`, in English.
- Give each ADR a sequential identifier and descriptive filename, such as `docs/ADR/adr-0001-domain-independence-from-eloquent.md`.
- Describe the context, decision, status, alternatives considered, and consequences, including relevant trade-offs, security concerns, and operational effects.
- Link related and superseding ADRs. Preserve the history of accepted decisions and mark superseded records explicitly rather than silently rewriting them.

## Persistence and Eloquent

Keep domain aggregates separate from Eloquent persistence models, with explicit repository mapping, as decided in [ADR-0001](ADR/adr-0001-domain-independence-from-eloquent.md). Manual mapping is the cost of this separation.

Expose `id()` and one immutable `state(): {Aggregate}StateDto` contract for repository mapping, as defined in [ADR-0002](ADR/adr-0002-aggregate-state-for-eloquent-persistence.md). This exposes persisted domain state while keeping mutations inside aggregate behavior. State DTOs are internal domain contracts, separate from query DTOs and integration events.

- Use Eloquent's native Active Record pattern for persistence models.
- Keep Eloquent models and their persistence behavior in infrastructure adapters, behind the core's ports. Domain objects and application contracts must not expose Eloquent models, builders, or collections.
- Use Eloquent relationships, scopes, casts, and transactions where appropriate within those adapters.
- Do not replace Eloquent with Doctrine or introduce a custom Data Mapper architecture to enforce framework independence.
- Use repositories and native Eloquent models to reconstitute aggregates and persist root and child state consistently. Reconstitution validates stored state without replaying commands, rechecking historical actions against current eligibility policies, or emitting new domain events. Incompatible stored state requires an explicit migration or compatibility policy.
- Expose write access through aggregate-root repositories, not repositories for each child entity. Do not let controllers, query adapters, model observers, relationship cascades, or bulk updates bypass aggregate invariants or concurrency protection. Eloquent lifecycle events are not substitutes for domain events.

## CQRS

- Separate commands that change state from queries that retrieve data.
- Execute commands through application command handlers; domain behavior enforces invariants.
- Keep the read side simple: invoke query services or query handlers directly, without a query bus.
- Let read implementations retrieve projections or read models through infrastructure adapters without reconstructing aggregates solely for display.
- Queries must not change business state. Separate read and write models do not require separate databases.
- Read DTOs and projections may combine data within a context without loading aggregates, but may be stale. Never use a projection as the authoritative basis for a write-side invariant; reload and validate the relevant aggregate within the command's consistency boundary.

## Aggregate and transaction boundaries

Follow Vaughn Vernon's approach: model actual invariants, keep aggregates small, reference other aggregates by identity, and use eventual consistency between independent aggregates.

- A state-changing command should modify one business aggregate instance per transaction, including its internal entities and value objects. Revisit the model when an immediate invariant appears to span aggregates; document any justified exception in an ADR.
- Application handlers define the use-case transaction boundary through `TransactionManagerInterface`; the infrastructure implementation uses a specific Laravel PostgreSQL connection's `transaction()` method. Repositories participate in that transaction and must not independently commit partial changes.
- Commit aggregate changes, the command/inbox processing outcome, audit records, and outbox entries on the same database connection in one transaction. These supporting records do not represent additional business aggregates. An exception must roll back all of them; using the same PostgreSQL server through different connections is not sufficient for atomicity.
- After rollback, record terminal rejections or failed attempts in a separate transaction where an audit record is required. Persist a terminal rejection together with its deduplication outcome so that a duplicate receives the same result. Do not report rolled-back business changes as committed or mark a retryable failure as successfully processed.
- Keep references to other aggregates as typed identifiers. Do not traverse Eloquent relationships to mutate a different aggregate in the same operation. Cross-context interactions use explicit application/integration contracts.
- Persist workflow progress in its own consistency boundary and exchange durable commands and outcomes. Each command has its own transaction; do not wrap the orchestrator and all participating aggregates in one outer transaction or let synchronous event handlers expand a command's write boundary.
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
- Translate domain events in the application layer when consumers need the information.
- Publish through `IntegrationEventPublisherInterface`. Its infrastructure implementation records publication intent in the transactional outbox on the same connection and transaction as the aggregate change; it does not invoke remote consumers inline.
- Transport adapters handle external delivery through the receiver's public contract; services must not share queue tables. Keep subscribers in `Application/IntegrationEventSubscriber` framework-independent; infrastructure jobs adapt delivery to those subscribers.
- Preserve explicit orchestration: subscribers invoke defined use cases or orchestrators in separate transactions. Listener chains must not implicitly define business workflows. Delivery, duplicate handling, and receiver obligations follow the reliable messaging rules below.

## PostgreSQL-backed queues

Use PostgreSQL for internal Laravel jobs, including local dispatch of committed outgoing messages and processing of incoming messages. Use RabbitMQ for external signal and result messaging, as selected in [ADR-0003](ADR/adr-0003-rabbitmq-for-integration-messaging.md). RabbitMQ integration is pending.

- Keep enqueueing, reservation, acknowledgement, retry, and failed-job handling in infrastructure adapters; the core must remain independent of queue implementation details.
- Run a recoverable dispatcher for committed outbox entries, preserving message identities across enqueue attempts. Workers must process committed state and safely handle retries and concurrent delivery under the reliable messaging rules below.
- Decide enqueue transaction behavior explicitly. In the current skeleton the database queue has `after_commit=false`; an insertion on the same connection inside the business transaction can be atomic with it. Inserts through another connection are outside that transaction. Laravel's `after_commit=true` or `afterCommit()` defers dispatch, but a process crash after commit can still prevent dispatch. Required publication must use a durable outbox and recoverable dispatcher, since an in-memory after-commit callback cannot survive that crash. See [jobs and database transactions](https://laravel.com/framework/docs/13.x/queues#jobs-and-database-transactions).

## Idempotency and reliable messaging

Retries, duplicate messages, timeouts, and restarts must not repeat business effects. A transactional Outbox makes outgoing messages durable and retryable; receivers need deduplication and recovery to prevent repeated execution and complete processing. An Outbox alone guarantees neither completion nor exactly-once effects.

- Give each retryable operation an idempotency key with a defined scope and retention period. Store its processing state and result durably. Return the stored result for completed duplicates; reject the same key with a different payload. New instructions need new keys.
- Enforce key uniqueness atomically in PostgreSQL. Scope instruction keys by the agreed business boundary, and inbox entries by consumer and message identity. A lookup before insertion cannot prevent concurrent duplicates.
- Use an Inbox for receivers we control when durable deduplication is needed. Commit the processing result with the business changes. If intake and execution are separate, acknowledge acceptance only after durably storing the message and responsibility for processing it. Acknowledge completion only after committing the result, and keep pending entries recoverable.
- Use a transactional Outbox whenever a state change must produce an outgoing message, following the atomic write rules above. Publish committed entries through a recoverable dispatcher.
- Expect at-least-once delivery, duplicate publication, and out-of-order messages. Define retry limits, retention, terminal failure handling, and recovery. Retries cannot guarantee success if a receiver stays unavailable or rejects a message. Delivery failures after commit require recovery or compensation; they cannot roll back the business change.
- Document and verify external receivers' guarantees. Track publication, durable acceptance, and business completion separately. Completion requires an explicit result or status contract.
- Pass stable idempotency keys to external systems where supported. Resolve an uncertain outcome before retrying an irreversible action. If the receiver offers neither deduplication nor reliable status checks, stop automatic retries and require explicit recovery.

## Concurrency control

Choose locking for each invariant based on how often operations conflict, the consistency needed, and the cost of a conflict.

- Use optimistic locking when conflicts are rare. Atomically update by aggregate identity and expected version, increment the version, and require exactly one affected row. Zero rows means a conflict: reject the operation or reload and re-evaluate it before retrying. Eloquent `save()` does not perform this check automatically, and `updated_at` alone is insufficient.
- Use pessimistic locking when operations need exclusive access to shared state. Lock the aggregate root before loading decision-making state. Every writer must follow this protocol. Keep transactions short, acquire locks in a consistent order, and release them before remote calls.
- Load a consistent snapshot of the root and its children before checking domain rules. PostgreSQL's default Read Committed isolation can return different committed versions across reads. For optimistic loading, read the root version before and after loading children; reload if it changed, and use the original version for the write. A suitable transaction snapshot is another option. Repositories must load all required state; domain behavior must not rely on Eloquent lazy loading or stale cached snapshots.
- Every write affecting an invariant must update the root version or lock the root, including child-only updates and inserts. Save root and child changes in one transaction. A failed version check must roll back child changes and outbox records too.
- Enforce uniqueness and integrity constraints in the database. Application checks followed by unprotected writes and process-local locks cannot protect concurrent workers or service instances.
- For competing inserts or limits across rows, choose a unique/exclusion constraint, a lock on a stable owning aggregate row, an atomic conditional update, or serializable isolation with whole-transaction retries. Row locks and isolation alone do not automatically protect conditions over sets of rows or rows that do not yet exist. Document the choice for each invariant; see PostgreSQL's [isolation](https://www.postgresql.org/docs/18/transaction-iso.html) and [locking](https://www.postgresql.org/docs/18/explicit-locking.html) documentation.
- Handle deadlocks, lock timeouts, and version conflicts explicitly. Limit retries and keep every attempt idempotent. Laravel can retry supported database concurrency errors, but application version conflicts need explicit handling. Reload aggregates and recreate event buffers on every attempt; never reuse mutated objects or make remote calls inside a retried callback. See [transaction retries](https://laravel.com/framework/docs/13.x/database#handling-deadlocks).

### Required consistency verification

Follow [testing-strategy.md](testing-strategy.md) for the test pyramid, PHPUnit coverage, Behat/Gherkin flows, mutation testing, and planned contract/E2E tests.

Before delivering a write use case, verify that:

- Domain tests reject invalid transitions and validate invariants, reconstitution, and value objects.
- PostgreSQL tests use independent connections and controlled operation ordering to cover inconsistent root/child reads, stale versions, child-only changes, competing inserts, pessimistic locks, and applicable deadlock/serialization retries.
- Failure tests confirm that aggregate, inbox, audit, and outbox writes roll back together, and concurrent duplicate instructions cause one business effect.
- Recovery tests cover crashes between commit and publication, duplicate and out-of-order messages, receiver failure before acknowledgement, lost acknowledgements, acceptance without completion, workflow restarts, and uncertain external outcomes.
- Deptrac rejects forbidden layer and context dependencies. Adapter registration works with cached routes and configuration.

The existing Eloquent CRUD test covers basic database integration; aggregate behavior and concurrency still need use-case implementations and tests.

## Explicit orchestration

- Use application orchestrators or process managers as the default for multi-step business workflows. Keep workflow order, state transitions, retries, timeouts, and compensation visible in the orchestrator.
- Coordinate participants through ports and application use cases. Domain behavior enforces business invariants.
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
- The analytics application owns analytical decisions; this service owns execution validity and its domain invariants.
- Trace execution instructions through logs and audit records, and apply the idempotency and recovery rules above to prevent repeated execution.
- Expose execution status, results, and failures through defined integration contracts.
