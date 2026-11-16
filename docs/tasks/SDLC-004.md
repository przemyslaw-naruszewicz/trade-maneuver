---
id: SDLC-004
status: done
---

# SDLC-004: Complexity checks in CI

## Goal

CI fails on code shapes that the current complexity check lets through: long chains of conditions, many sequential conditions, long methods, long parameter lists on ordinary methods, empty `catch` blocks, and unused local variables. The class limit for cognitive complexity comes down from 40 to 30, so it starts to limit how far classes grow. Today's code passes every new check.

## Scope

In scope:

- The PHPStan configuration, for the class limit only.
- `phpmd/phpmd` as a dev dependency, a project ruleset, and a Composer script that `ci:checks` runs.
- [docs/development.md](../development.md#ci-quality-checks), "CI quality checks".

Out of scope:

- PHPMD rules beyond the six in Requirements, and limits other than PHPMD's defaults.
- Refactoring existing code.
- A new CI job or a change to the GitHub Actions workflow.
- Checks for development calls such as `var_dump()` and for long `match` expressions.

## Requirements

### PHPStan class limit

- Lower `cognitive_complexity.class` from 40 to 30. The function limit stays at 9.

### PHPMD

- Add `phpmd/phpmd` ^3.0 as a dev dependency. Resolving it against the current lock adds `phpmd/phpmd` 3.0.0 and `pdepend/pdepend` 3.1.0 and changes no other package.
- Add a project ruleset with exactly these six rules, each at PHPMD's default limit:

  | Rule | Limit | What it catches that no current check catches |
  | --- | --- | --- |
  | `CyclomaticComplexity` | 10 | A long chain of `&&`/`\|\|`, which cognitive complexity counts once |
  | `NPathComplexity` | 200 | Many sequential conditions, for example 8 non-nested `if` statements (NPath 256, cognitive complexity 8) |
  | `ExcessiveMethodLength` | 100 lines | A long method without branches |
  | `ExcessiveParameterList` | 10, with `__construct` in the rule's `exceptions` property | A long parameter list on an ordinary method |
  | `EmptyCatchBlock` | none | A swallowed failure, which [architecture.md](../architecture.md) forbids |
  | `UnusedLocalVariable` | none | A dead assignment |

- Exempt constructors only through the ruleset's `exceptions` property. Add no `#[SuppressWarnings]` attributes.
- Add a Composer script, for example `analyse:phpmd`, that runs PHPMD with the project ruleset on `src`, `app`, and `tests`, and fails on any violation.
- Add the script to `ci:checks`, so that `ci`, `ci:diff`, `make ci`, and GitHub Actions run it.

### Documentation

- In [docs/development.md](../development.md#ci-quality-checks), "CI quality checks", the PHPStan row states a class limit of 30, and a new row names PHPMD and its ruleset file.

## Decisions

- The owner chose a class limit of 30 on 2026-11-16, over 25 and 20. The highest class score today is 19 (`MessageFields`), so no class needs to change.
- The owner approved the `phpmd/phpmd` dev dependency, with `pdepend/pdepend`, on 2026-11-16.
- PHPMD checks `tests` as well as `src` and `app` (owner decision, 2026-11-16). Empty `catch` blocks, dead assignments, and oversized methods are defects in tests too, and the six rules pass on today's tests.
- `__construct` is exempt from the parameter limit for every class (owner decision, 2026-11-16). The two constructors over 10 parameters, `PublishTradeSignalCommand` (12) and `Order` (11), only assign promoted properties, and splitting them would add structure that no requirement asks for. `#[SuppressWarnings]` on the two classes was rejected, because it was not tested and would put a PHPMD attribute into the domain.
- A comparison of tools rejected PHP_CodeSniffer with Slevomat, PHPInsights, PHPMetrics, and churn-php in favour of these six PHPMD rules.
- `CouplingBetweenObjects`, `TooManyMethods`, `TooManyPublicMethods`, `NumberOfChildren`, and the `cleancode`, `naming`, and `controversial` rulesets stay out. On today's code they report only noise: value objects count as dependencies, accessors have no `get` prefix, test methods are snake case, and every test extends one base class.
- Break checks verify the new checks through their commands, at the project tool seam in the [testing strategy](../testing-strategy.md#seams). No fixture tests are committed (owner decision, 2026-11-16): PHPMD has no counterpart to PHPStan's `RuleTestCase`, and a custom harness would be tooling that an installed tool could replace.

## Constraints

- The Composer `scripts` change is a CI configuration change: run `make ci-lint`, then `make ci`, then check the PR's GitHub Actions run ([SDLC §6](../../SDLC.md#changes-to-ci-configuration)).
- Probes use only fictional names, for example `DEMO_A`, and are not committed.

## Risks

- PHPMD 3 and PDepend 3 are new release lines (October and November 2026) that still receive PHP 8.4 and 8.5 parser fixes. The code does not use that syntax yet, so the comparison did not exercise it. A parser failure on new syntax would block CI until an update or a workaround.

## Acceptance criteria

- [x] Break check: a temporary class with cognitive complexity above 30 makes `composer analyse` fail, and `composer analyse` passes without it.
- [x] Break check: the PHPMD script fails for each of six temporary probes, one per rule: a 12-condition `||` chain, 8 sequential `if` statements, a 101-line method, an ordinary method with 11 parameters, an empty `catch` block, and an unused local variable. It passes without the probes.
- [x] Break check: a temporary constructor with 11 promoted parameters passes the PHPMD script.
- [x] Tool output: `composer.lock` gains only `phpmd/phpmd` and `pdepend/pdepend`, and `ci:checks` runs the PHPMD script; `make ci` shows the PHPMD step and passes.
- [x] Documentation check: [docs/development.md](../development.md#ci-quality-checks) states the class limit of 30 and lists PHPMD with its ruleset.

## Dependencies

- Blocked by: none.
