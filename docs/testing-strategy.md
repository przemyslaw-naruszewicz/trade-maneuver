# Testing strategy

Test observable business behavior against the modeling rules in [ddd.md](ddd.md) and the guarantees in [architecture.md](architecture.md), including retries, concurrency, and partial failure. This is the target strategy; the skeleton currently has PHPUnit coverage for application availability, user serialization and casts, PostgreSQL integration, and the autoloading and registration of the Trading context, plus a Behat health-check scenario. The investment configuration has domain unit tests, PostgreSQL mapping and atomicity tests, and a console command feature test. Trade signal intake has domain and application unit tests, PostgreSQL mapping, atomicity, and concurrency tests, and Behat scenarios at the Trading application boundary. The RabbitMQ consumer and the outbox dispatcher have integration tests against RabbitMQ and PostgreSQL, which run the consumer and the dispatcher as separate processes where the test needs acknowledgement, redelivery, or signal handling.

## Test pyramid

Build a test pyramid with a broad base of fast PHPUnit unit tests, focused integration coverage, and a small set of complete system journeys at the top. Place each assertion at the lowest level that can prove the required behavior; avoid repeating the same cases across every level.

| Scope | Tool | Purpose | Target adoption |
| --- | --- | --- | --- |
| Unit tests: broad base | PHPUnit | Domain behavior, invariants, value objects, and isolated application logic | Required |
| Focused technical integration tests | PHPUnit | Repository mapping, PostgreSQL transactions, adapters, and messaging mechanisms | Required |
| Business-flow integration scenarios | Behat and Gherkin | Real flows through the application and its local infrastructure | Required |
| Contract tests at integration boundaries | Tool to be selected | Compatibility between this service, the analytics application, and other providers or consumers | Planned |
| Small E2E suite: pyramid top | Tool to be selected | Critical journeys through a deployed system, including browser interaction where applicable | Planned |

PHPUnit, Behat, and Infection are installed and included in CI. The planned contract/E2E tools are not installed. Infection uses mutation testing to check whether the tests at the covered levels of the pyramid detect changes in behavior.

## Seams

A seam is the public interface a test works through: the test supplies input and observes behavior there, without reaching into the internals, so the internals can change without breaking the test. Michael Feathers' *Working Effectively with Legacy Code* uses the term differently, for a place where behavior can be changed without editing the code there. Tests use these kinds of seam:

- The application boundary of a bounded context: Behat scenarios and PHPUnit application tests.
- The public behavior of an aggregate or value object: domain unit tests, without Laravel or a database.
- An adapter or project tool at its own boundary, with its real infrastructure: focused integration and feature tests. Business rules stay at the two seams above.
- Planned: the boundary between two systems for contract tests, and a deployed system's public interfaces for E2E tests.

At the application boundary, substitute only ports to what the test cannot control, such as external systems and time. Everything the context owns runs for real.

A task names its concrete ports and adapters. A new port, adapter, or bounded context uses these kinds without further confirmation. A new kind of seam needs the project owner's confirmation and an update here.

## PHPUnit

- Use PHPUnit as the PHP test framework. Keep domain unit tests independent of Laravel and the database.
- Test valid and rejected state transitions, boundary values, exact monetary calculations, and the immutability of value objects and aggregate state DTOs. Assert meaningful outcomes rather than private implementation details.
- Use isolated application tests for orchestration decisions and failure handling, substituting dependencies at explicit ports where appropriate.
- Use focused PHPUnit integration tests for infrastructure behavior that needs a real implementation: Eloquent/domain mapping, reconstitution, constraints, locking, transaction rollback, Inbox/Outbox atomicity, and queue processing. Verify that reconstitution preserves stored values without emitting new domain events or reapplying current eligibility policies to historical actions. Mocked database calls cannot establish these guarantees.
- Use existing factories and domain builders for setup. Add dedicated mapping tests when round-trip behavior is non-trivial or no meaningful integration test already exercises it.

## Behat and Gherkin business flows

Use Behat to execute integration scenarios written in [Gherkin](https://docs.behat.org/en/latest/user_guide/features_scenarios.html). Scenarios describe real business flows and observable outcomes in the ubiquitous language.

- Cover request-driven business flows through Behat integration scenarios. Keep focused PHPUnit tests for transport concerns such as authentication, authorization, input validation, and response mapping where needed, without duplicating the business-flow suite.
- Exercise the real application wiring, use cases, domain model, and local PostgreSQL infrastructure. Framework bootstrapping and request helpers may be used behind context steps; the use-case handlers must run their real implementations.
- Use `Given` to establish business state through application commands or domain factories, `When` to perform the relevant public action, and `Then` to verify results through queries, responses, and observable integration outcomes.
- Keep steps readable and reusable. Use business aliases rather than raw identifiers, and keep framework, database, and transport details inside step implementations.
- Cover successful execution, signal rejections and declines, duplicate trade signals, retries, and recoverable failures. The most detailed concurrency interleavings belong in focused integration tests.
- Replace uncontrolled external systems with deterministic test adapters or test servers. A scenario using such replacements is local integration coverage; deployed cross-system E2E coverage is planned separately.

For example:

```gherkin
Feature: Idempotent trade signals
  Scenario: Redelivery does not repeat a filled order
    Given a trade signal named "purchase" has ended with an order fill
    When the analytics application redelivers the same trade signal
    Then the stored signal outcome is returned
    And no additional order is submitted to the broker
    And the duplicate delivery is traceable in the audit history
```

## Real infrastructure and failure testing

- Run persistence and messaging integration tests against a separate PostgreSQL test database and the separate `trade-maneuver-testing` RabbitMQ vhost. Isolate test data and keep tests deterministic.
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
- Pull-request check: PR CI runs Infection on changed lines with the configured thresholds. A surviving mutant on a changed line that PHPUnit covers fails the PR. A PR with no changes in the configured sources, or with changes that generate no mutations, passes.
- Infection runs PHPUnit only, so it does not measure Behat scenarios. The pull-request check ignores lines PHPUnit does not run, because a line only Behat exercises would otherwise fail the PR as untested. Red commits are the evidence for those lines. A line no test exercises also passes this check; the independent review and the full run on `main`, which counts uncovered lines, cover that gap.

## Planned contract tests

- Add automated contract tests for trade signal intake, signal outcomes, status APIs, and integration events exchanged with analytics applications, the broker, and other external systems.
- Verify payloads, versions, exact amount representations, error responses, idempotency semantics, and acknowledgement/completion contracts. Verify provider behavior and consumer expectations together.
- Keep compatibility checks independent of full E2E journeys.

## Planned automated E2E tests

- Add a small automated suite covering critical journeys through a deployed test environment, including the actual browser and JavaScript when the GUI requires them.
- Include trade signal submission, operator approval, execution progress, outcome reporting, and recovery journeys across real process boundaries. Use the simulated broker, never a real broker or a live brokerage account.
- Keep E2E coverage focused on wiring and critical user/system journeys; keep detailed business-rule and failure permutations in the faster levels of the pyramid.

## Delivery and adoption

- Follow the [SDLC contract](../SDLC.md) for task acceptance criteria, independent AI review, merge gates, and the planned deployment requirements.
- For CI and environment configuration changes, follow the [CI change verification process](../SDLC.md#changes-to-ci-configuration). Run `make ci-lint` locally; GitHub Actions also runs it before the application pipeline.
- A change must include tests at the level that proves its behavior, written [test-first](../SDLC.md#test-first-implementation). Run the narrowest relevant tests during development and the applicable automated suites in CI.
- Treat PHPUnit, Behat integration scenarios, Infection, and architecture checks as required parts of the target strategy. Contract and automated E2E suites are planned additions.
- Run the shared local and GitHub Actions pipeline with `make ci`; see [README](../README.md#quality-checks) for its checks, mutation thresholds, and reports. GitHub Actions runs Infection on changed lines for pull requests and on all configured sources for pushes to `main` and manual dispatch. Local `make ci` includes the full Infection run by default; `CI_WITH_MUTATION=diff make ci` runs the pull-request checks, and `CI_WITH_MUTATION=0 make ci` skips Infection. PHPStan with Larastan and PHP CS Fixer are also configured. Deptrac runs in CI and enforces the layer rules for the Trading context and the application code under the root namespace, and a PHPStan check keeps functions from installed packages out of `Domain` and `Application`.
- PHPUnit's source coverage, Infection's source directories, and PHPStan's paths include both `app/` and `src/`. A passing run that omits the intended domain and application code provides no evidence about it.
- The existing skeleton tests cover only basic integration. Add Behat scenarios as business flows are implemented, and keep PHPUnit adapter tests focused on technical behavior.
