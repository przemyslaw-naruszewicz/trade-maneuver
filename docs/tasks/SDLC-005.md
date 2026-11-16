---
id: SDLC-005
status: draft
---

# SDLC-005: Architecture checks with PHPat

## Goal

CI fails on core dependencies that Deptrac does not see and on classes whose shape breaks the agreed rules. PHPat repeats the `Domain` and `Application` rows of the dependency matrix as PHPStan rules. It reports a `Domain` enum that implements an `Application` interface and a framework attribute on a `Domain` class constant, and it checks that classes are `final`, that interfaces carry the `Interface` suffix, and that integration events are immutable. Deptrac keeps the full layer matrix. Today's code passes every new check.

## Scope

In scope:

- `phpat/phpat` as a dev dependency and its rules, run by `composer analyse`.
- [docs/architecture.md](../architecture.md#dependency-rules), for which tool enforces which rule.
- [docs/coding-standards.md](../coding-standards.md), "Types and naming", for the `final` rule.
- [docs/development.md](../development.md#ci-quality-checks), "CI quality checks".

Out of scope:

- Changes to the Deptrac configuration or the layer matrix.
- PHPat rules for `app/`, `Infrastructure`, or `Ui`.
- Refactoring existing code.
- A new CI step or a change to the GitHub Actions workflow.

## Requirements

### PHPat

- Add `phpat/phpat` ^0.12 as a dev dependency. Resolving it against the current lock adds only `phpat/phpat`. It is a PHPStan 2 extension, so `composer analyse` reports its rules and CI gets no new step.
- Set `ignore_built_in_classes: true`, so that PHP's own classes, such as `DateTimeImmutable` and the built-in exceptions, are allowed.

### Rules

Each rule applies to both namespaces, `Trading` and `DemoAnalytics`, and selects classes in `src` only.

- `Domain` depends only on its own `Domain` and on `Brick\Money` and `Brick\Math`.
- `Application` depends only on its own `Application` and `Domain` and on the PSR contracts its Deptrac row allows: `Psr\Clock` in both namespaces, and `Psr\Log` in Trading.
- Classes in `Application\IntegrationEvent` are `readonly`.
- Concrete classes are `final`. Abstract classes, among them abstract exception bases, and enums, interfaces, and traits are exempt.
- Interfaces end with `Interface`.

### Deptrac

- The Deptrac configuration stays unchanged and keeps the full layer matrix. PHPat repeats only the `Domain` and `Application` rows. A later change to the dependencies allowed in the core changes both tools together.

### Documentation

- [docs/coding-standards.md](../coding-standards.md), "Types and naming": concrete application-owned classes are `final`, with the exemptions above. The existing `Interface` suffix rule stays as it is.
- [docs/architecture.md](../architecture.md#dependency-rules), the enforcement text after the dependency matrix: Deptrac enforces the layer matrix; PHPat repeats the `Domain` and `Application` rows to close Deptrac's gaps, an enum's `implements` and an attribute on a class constant, and checks class shape; the PHPStan disallowed-calls entries keep package functions out of the core and limit calls to `state()`. Keep the sentence on what no tool detects.
- [docs/development.md](../development.md#ci-quality-checks), "CI quality checks": the PHPStan row names PHPat, and the sentence after the table names the rules that Deptrac, PHPat, and the disallowed-calls entries enforce.

## Decisions

- PHPat over PHPArkitect: in a comparison, PHPArkitect missed a dependency declared only in PHPDoc generics and would run as a separate CLI step. PHPArkitect stays the fallback if the 0.x release line of PHPat causes problems.
- Deptrac stays, because PHPat selects classes only and cannot check the root files in `config`, `routes`, `bootstrap`, and `database`.
- Pest's architecture plugin is out, because the project uses PHPUnit.
- The owner approved the `phpat/phpat` dev dependency, a 0.x release line, on 2026-11-16.
- `final` concrete classes become a documented rule in coding-standards.md, and PHPat enforces it together with the existing `Interface` suffix rule (owner decision, 2026-11-16). All 116 concrete classes and 14 interfaces in `src` already follow both.
- The rules cover `src` only (owner decision, 2026-11-16). Classes in `app/` such as Eloquent models, Livewire components, and service providers extend framework classes, and Deptrac already checks the root namespace.
- Comparison evidence for probes in `Trading\Domain`:

  | Probe | Deptrac | PHPat |
  | --- | --- | --- |
  | Enum that implements an `Application` interface | missed | reported |
  | `Illuminate` attribute on a class constant | missed | reported |
  | Dependency only in PHPDoc generics | reported | reported |
  | Class type, static call, `new` of a class from the other namespace | reported | reported |
  | Unqualified `config()` call | missed (the PHPStan disallowed-calls entry reports it) | missed |
  | Non-final class, interface without the suffix, mutable integration event | no such rule | reported |

- Break checks verify the rules through `composer analyse`, at the project tool seam in the [testing strategy](../testing-strategy.md#seams). No fixture tests are committed (owner decision, 2026-11-16).

## Constraints

- The PHPStan configuration change runs in CI, so run `make ci` and check the PR's GitHub Actions run.
- Probes use only fictional names, for example `DEMO_A`, and are not committed.

## Risks

- PHPat is a 0.x release, so its rule API may change in a minor release.
- Deptrac and PHPat keep the allowed core dependencies in two places, which must change together.

## Acceptance criteria

- [ ] Break check: in both `Trading` and `DemoAnalytics`, a temporary `Domain` enum that implements an `Application` interface makes `composer analyse` fail, and so does a temporary `Illuminate` attribute on a `Domain` class constant.
- [ ] Break check: a temporary `Domain` class that depends on a package other than `Brick\Money` or `Brick\Math`, and a temporary `Application` class that depends on `Infrastructure`, each make `composer analyse` fail.
- [ ] Break check: a temporary class in `Application\IntegrationEvent` that is not `readonly` makes `composer analyse` fail.
- [ ] Break check: a temporary non-final concrete class and a temporary interface without the `Interface` suffix in `src` each make `composer analyse` fail, while an abstract class, an enum, and a trait pass.
- [ ] Tool output: `composer analyse` passes without the probes, `composer.lock` gains only `phpat/phpat`, the Deptrac configuration is unchanged, and `composer analyse:architecture` passes.
- [ ] Documentation check: coding-standards.md, architecture.md, and development.md state the rules in Documentation.

## Dependencies

- Blocked by: none.
