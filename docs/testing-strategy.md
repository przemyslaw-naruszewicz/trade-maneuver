# Testing strategy

Test observable business behavior against the modeling rules in [ddd.md](ddd.md) and the guarantees in [architecture.md](architecture.md), including retries, concurrency, and partial failure. This is the target strategy; the skeleton currently has PHPUnit coverage for application availability, user serialization and casts, and PostgreSQL integration, plus a Behat health-check scenario. Business-flow coverage still needs implementation.

## Test pyramid

Build a test pyramid with a broad base of fast PHPUnit unit tests, focused integration coverage, and a small set of complete system journeys at the top. Place each assertion at the lowest level that can prove the required behavior; avoid repeating the same cases across every level.

| Scope | Tool | Purpose | Target adoption |
| --- | --- | --- | --- |
| Unit tests: broad base | PHPUnit | Domain behavior, invariants, value objects, and isolated application logic | Required |
| Focused technical integration tests | PHPUnit | Repository mapping, PostgreSQL transactions, adapters, and messaging mechanisms | Required |
| Business-flow integration scenarios | Behat and Gherkin | Real flows through the application and its local infrastructure | Required |
| Contract tests at integration boundaries | Tool to be selected | Compatibility between this service, the analytics application, and other providers or consumers | Planned |
| Small E2E suite: pyramid top | Tool to be selected | Critical journeys through a deployed system, including browser interaction where applicable | Planned |

PHPUnit, Behat, and Infection are installed and included in CI. The planned contract/E2E tools are not installed. Contract tests check compatibility at system boundaries. Infection evaluates whether tests detect changes in behavior through mutation testing at the covered levels of the pyramid.

## PHPUnit

- Use PHPUnit as the PHP test framework. Keep domain unit tests independent of Laravel and the database.
- Test valid and rejected state transitions, boundary values, exact monetary calculations, and the immutability of value objects and aggregate state DTOs. Assert meaningful outcomes rather than private implementation details.
- Use isolated application tests for orchestration decisions and failure handling, substituting dependencies at explicit ports where appropriate.
- Use focused PHPUnit integration tests for infrastructure behavior that needs a real implementation: Eloquent/domain mapping, reconstitution, constraints, locking, transaction rollback, Inbox/Outbox atomicity, and queue processing. Verify that reconstitution preserves stored values without emitting new domain events or reapplying current eligibility policies to historical actions. Mocked database calls cannot establish these guarantees.
- Use existing factories and domain builders for setup. Add dedicated mapping tests when round-trip behavior is non-trivial or not already exercised by meaningful integration coverage.

## Behat and Gherkin business flows

Use Behat to execute integration scenarios written in [Gherkin](https://docs.behat.org/en/latest/user_guide/features_scenarios.html). Scenarios describe real business flows and observable outcomes in the ubiquitous language.

- Cover request-driven business flows through Behat integration scenarios. Keep focused PHPUnit tests for transport concerns such as authentication, authorization, input validation, and response mapping where needed, without duplicating the business-flow suite.
- Exercise the real application wiring, use cases, domain model, and local PostgreSQL infrastructure. Framework bootstrapping and request helpers may be used behind context steps; the use-case handlers must run their real implementations.
- Use `Given` to establish business state through application commands or domain factories, `When` to perform the relevant public action, and `Then` to verify results through queries, responses, and observable integration outcomes.
- Keep steps readable and reusable. Use business aliases rather than raw identifiers, and keep framework, database, and transport details inside step implementations.
- Cover successful execution, business rejection, duplicate instructions, retries, and recoverable failures. The most detailed concurrency interleavings belong in focused integration tests.
- Replace uncontrolled external systems with deterministic test adapters or test servers. A scenario using such replacements is local integration coverage; deployed cross-system E2E coverage is planned separately.

For example:

```gherkin
Feature: Idempotent execution instructions
  Scenario: Redelivery does not repeat a completed execution
    Given an instruction named "purchase" has completed successfully
    When the analytics application redelivers the same instruction
    Then the recorded execution result is returned
    And no additional execution is requested from the execution provider
    And the duplicate delivery is traceable in the audit history
```

## Real infrastructure and failure testing

- Run persistence and messaging integration tests against a separate PostgreSQL test database. Isolate test data and keep tests deterministic.
- Use independent database connections and controlled interleavings to verify concurrency, including changes between root and child reads. Commit setup data before workers use it, and clean up records committed by every connection. A test wrapped in one connection's transaction cannot prove concurrent-worker behavior.
- Exercise the PostgreSQL-backed queue when testing queue and worker guarantees. The current PHPUnit configuration uses the `sync` queue driver; these scenarios require explicit test configuration using the database driver.
- Test atomic rollback across aggregate, inbox, audit, and outbox writes. Cover crashes before publication, duplicate and out-of-order messages, lost acknowledgements, and recovery after worker failure. Verify that terminal rejections retain a deduplicated outcome after rollback and that accepted but unprocessed inbox entries remain recoverable.
- Distinguish durable receiver acceptance from business completion. Verify idempotency and reconciliation for uncertain external outcomes rather than assuming that an acknowledgement or an outbox record proves successful processing.
- Enforce architecture boundaries with Deptrac as a separate automated quality check.

## Mutation testing with Infection

- Use Infection as the required mutation-testing tool alongside PHPUnit. It introduces controlled changes into code to check whether the tests detect them; see the [Infection guide](https://infection.github.io/guide/).
- Prioritize domain rules, boundary conditions, monetary calculations, and application decisions. Run the relevant passing test suite before evaluating mutations.
- Investigate surviving mutants and improve assertions or missing cases. Review equivalent mutants explicitly rather than adding artificial tests or broad exclusions to improve a score.
- Treat code coverage and mutation results as supporting evidence, not proof of correctness. Establish explicit mutation thresholds in CI as the meaningful suite grows; do not claim guarantees solely from a percentage.

## Planned contract tests

- Add automated contract tests for instruction intake, execution results, status APIs, and integration events exchanged with the analytics application and other external systems.
- Verify payloads, versions, exact amount representations, error responses, idempotency semantics, and acknowledgement/completion contracts. Verify provider behavior and consumer expectations together where both sides are under our control.
- Keep compatibility checks independent of full E2E journeys. For providers we do not control, verify the published contract against an agreed test environment where available and document any limits.

## Planned automated E2E tests

- Add a small automated suite covering critical journeys through a deployed test environment, including the actual browser and JavaScript when the GUI requires them.
- Include instruction submission, execution progress, outcome reporting, and recovery journeys across real process boundaries. Use safe test environments for external execution systems.
- Keep E2E coverage focused on wiring and critical user/system journeys; retain detailed business-rule and failure permutations in the faster levels of the pyramid.

## Delivery and adoption

- A change must include tests at the level that proves its behavior. Run the narrowest relevant tests during development and the applicable automated suites in CI.
- Treat PHPUnit, Behat integration scenarios, Infection, and architecture checks as required parts of the target strategy. Contract and automated E2E suites are planned additions.
- Run the shared local and GitHub Actions pipeline with `make ci`; see [README](../README.md#quality-checks) for its checks, mutation thresholds, and reports. PHPStan with Larastan and PHP CS Fixer are also configured. Deptrac is installed and runs in CI with one placeholder layer and an empty ruleset; boundary enforcement remains pending.
- When introducing the target source layout, extend PHPUnit's source coverage configuration and Infection's source directories beyond `app/` to include `src/`, and add `src/` to PHPStan's paths. A passing run that omits the intended domain and application code provides no evidence about it.
- Existing skeleton tests cover only basic integration. Add Behat scenarios as business flows are implemented and keep PHPUnit adapter tests focused on technical behavior.
