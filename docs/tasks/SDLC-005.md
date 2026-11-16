---
id: SDLC-005
status: draft
---

# SDLC-005: Architecture checks with PHPat

## Goal

CI fails on dependencies in `src` that Deptrac does not see and on classes whose shape breaks the agreed rules. PHPat repeats the whole dependency matrix for `src` as PHPStan rules, for every layer of both namespaces. It reports an enum that implements an interface from a forbidden layer and an attribute from a forbidden layer on a class constant, and it checks that classes are `final`, that interfaces carry the `Interface` suffix, and that integration events are immutable. Deptrac keeps the full layer matrix, including the root files that PHPat cannot check. Today's code passes every new check.

## Scope

In scope:

- `phpat/phpat` as a dev dependency and its rules, run by `composer analyse`.
- [docs/architecture.md](../architecture.md#dependency-rules), for which tool enforces which rule.
- [docs/coding-standards.md](../coding-standards.md), "Types and naming", for the `final` rule.
- [docs/development.md](../development.md#ci-quality-checks), "CI quality checks".

Out of scope:

- Changes to the Deptrac configuration or to the dependency matrix.
- PHPat rules for code outside `src`: `app/`, `bootstrap/`, `config/`, `database/`, `routes/`, and `tests/`. Deptrac keeps checking the root namespace.
- Refactoring existing code.
- A new CI step or a change to the GitHub Actions workflow.

## Requirements

### PHPat

- Add `phpat/phpat` ^0.12 as a dev dependency. Resolving it against the current lock adds only `phpat/phpat`. It is a PHPStan 2 extension, so `composer analyse` reports its rules and CI gets no new step.
- Set `ignore_built_in_classes: true`, so that PHP's own classes, such as `DateTimeImmutable` and the built-in exceptions, are allowed.

### Dependency rules

PHPat repeats every row of the [dependency rules](../architecture.md#dependency-rules) for the classes in `src`, in both namespaces, `Trading` and `DemoAnalytics`. The layers are those Deptrac defines, and every layer may also depend on itself, as in Deptrac. The allowed libraries are `Brick\Money`, `Brick\Math`, `Psr\Clock`, and in Trading `Psr\Log`. Third-party code means, as in Deptrac's `ThirdParty` layer, every class outside `TradeManeuver\`, `App\`, `Database\`, the allowed libraries, and PHP itself. Each rule may use `canOnlyDependOn` or `shouldNotDependOn`, whichever states the row exactly.

| Layer in `src` | May depend on |
| --- | --- |
| `Domain` | Its own `Domain`, `Brick\Money`, and `Brick\Math` |
| `Application` | Its own `Application` and `Domain`, `Psr\Clock`, and in Trading `Psr\Log` |
| `Infrastructure`, apart from read models, query adapters, and the service provider | Its own `Application` and `Domain`, the allowed libraries, and third-party code |
| Trading read models in `Infrastructure/{Aggregate}/ReadModel` | Third-party code only |
| Trading query adapters, `Infrastructure/{Aggregate}/{Aggregate}Query` | The same as `Infrastructure`, and Trading read models |
| `Ui`, apart from Livewire components | Its own `Application` and `Domain`, the allowed libraries, and third-party code |
| Trading Livewire components in `Ui/Livewire` | The same as `Ui`, and Trading read models |
| The service provider, the composition root | Every layer of its own namespace, the allowed libraries, and third-party code |

These rules follow from the table:

- `Ui` and `Infrastructure` do not depend on each other, apart from the read-model rows.
- No class in one namespace depends on a class in the other.
- No class in `src` depends on `App\` or `Database\`.
- Read models depend on no allowed library, and no DemoAnalytics class depends on `Psr\Log`.

### Class shape

These rules select classes in `src` only, in both namespaces.

- Classes in `Application\IntegrationEvent` are `readonly`.
- Concrete classes are `final`. Abstract classes, among them abstract exception bases, and enums, interfaces, and traits are exempt.
- Interfaces end with `Interface`.

### Deptrac

- The Deptrac configuration stays unchanged and keeps the full layer matrix, including the root files that PHPat cannot select.
- The matrix for `src` now lives in two tools. A change to a row changes `deptrac.yaml`, the PHPat rules, and the table in architecture.md together.

### Documentation

- [docs/coding-standards.md](../coding-standards.md), "Types and naming": concrete classes in `src` are `final`, with the exemptions above. The existing `Interface` suffix rule stays as it is.
- [docs/architecture.md](../architecture.md#dependency-rules), the enforcement text around the dependency matrix: Deptrac enforces the whole matrix, including the root files; PHPat repeats the rows for `src` to close Deptrac's gaps, an enum's `implements` and an attribute on a class constant, and checks class shape; the PHPStan disallowed-calls entries keep package functions out of the core and limit calls to `state()`. State that a row changes in both tools together. Keep the sentence on what no tool detects.
- [docs/development.md](../development.md#ci-quality-checks), "CI quality checks": the PHPStan row names PHPat, and the sentence after the table names the rules that Deptrac, PHPat, and the disallowed-calls entries enforce.

## Decisions

- PHPat over PHPArkitect: in a comparison, PHPArkitect missed a dependency declared only in PHPDoc generics and would run as a separate CLI step. PHPArkitect stays the fallback if the 0.x release line of PHPat causes problems.
- Deptrac stays, because PHPat selects classes only and cannot check the root files in `config`, `routes`, `bootstrap`, and `database`.
- Pest's architecture plugin is out, because the project uses PHPUnit.
- The owner approved the `phpat/phpat` dev dependency, a 0.x release line, on 2026-11-16.
- PHPat repeats the whole matrix for `src`, not only the `Domain` and `Application` rows (owner decision, 2026-11-16). Deptrac's gaps affect every row: an `Infrastructure` enum that implements a `Ui` interface, or an attribute from the other namespace on a class constant, would also pass unreported. Checking only the core would leave the rules between `Ui` and `Infrastructure` and between the namespaces with the same gaps.
- `final` concrete classes become a documented rule for `src`, and PHPat enforces it together with the existing `Interface` suffix rule (owner decision, 2026-11-16). All 116 concrete classes and 14 interfaces in `src` already follow both. Code outside `src`, such as `app/Models/User.php` and `app/Providers/AppServiceProvider.php`, is not final today and stays out of the rule.
- Comparison evidence for probes in `Trading\Domain`:

  | Probe | Deptrac | PHPat |
  | --- | --- | --- |
  | Enum that implements an `Application` interface | missed | reported |
  | `Illuminate` attribute on a class constant | missed | reported |
  | Dependency only in PHPDoc generics | reported | reported |
  | Class type, static call, `new` of a class from the other namespace | reported | reported |
  | Unqualified `config()` call | missed (the PHPStan disallowed-calls entry reports it) | missed |
  | Non-final class, interface without the suffix, mutable integration event | no such rule | reported |

- The php-cs-fixer `final_class` fixer was considered for the `final` rule and rejected. Its rules apply to the whole Finder, which covers the whole repository, including the non-final classes in `app/` and `tests/`. It also cannot check the `Interface` suffix or `readonly` integration events, which PHPat checks in the same run.
- Break checks verify the rules through `composer analyse`, at the project tool seam in the [testing strategy](../testing-strategy.md#seams). No fixture tests are committed (owner decision, 2026-11-16). The break checks prove each rule when it is added, as in SDLC-004, so both tasks use one verification method. `RuleTestCase` fixtures like those in `tests/PHPStan` would need a fixture set per PHPat rule, and review catches a later weakening of the configuration.

## Constraints

- The PHPStan configuration change runs in CI, so run `make ci` and check the PR's GitHub Actions run.
- Probes use only fictional names, for example `DEMO_A`, and are not committed.

## Risks

- PHPat is a 0.x release, so its rule API may change in a minor release.
- The matrix for `src` lives in Deptrac and PHPat, which must change together. A row changed in one tool only leaves the other enforcing the old rule.
- The comparison tested PHPat only with probes in `Trading\Domain`. The rows for the other layers are new ground, which the break checks cover.

## Acceptance criteria

- [ ] Break check: in both `Trading` and `DemoAnalytics`, a temporary `Domain` enum that implements an `Application` interface makes `composer analyse` fail, and so does a temporary `Illuminate` attribute on a `Domain` class constant.
- [ ] Break check: a temporary `Infrastructure` enum that implements a `Ui` interface, and a temporary attribute from the other namespace on a `Ui` class constant, each make `composer analyse` fail.
- [ ] Break check: one temporary probe per row, each a dependency the row forbids and placed in `Trading` unless stated otherwise, makes `composer analyse` fail: `Domain` on an `Illuminate` class, `Application` on `Infrastructure`, `Infrastructure` on `Ui`, `Ui` on `Infrastructure`, a repository and a non-Livewire `Ui` class each on a read model, a read model on `Application`, a query adapter on `Ui`, a Livewire component on an `Infrastructure` class other than a read model, the service provider on the other namespace, a read model on `Brick\Money`, and a DemoAnalytics `Application` class and a DemoAnalytics `Infrastructure` class each on `Psr\Log`.
- [ ] Break check: a temporary class in `Trading` that depends on `DemoAnalytics`, one in `DemoAnalytics` that depends on `Trading`, and one in `src` that depends on `App\` each make `composer analyse` fail.
- [ ] Break check: a temporary class in `Application\IntegrationEvent` that is not `readonly` makes `composer analyse` fail.
- [ ] Break check: a temporary non-final concrete class and a temporary interface without the `Interface` suffix in `src` each make `composer analyse` fail, while an abstract class, an enum, and a trait pass.
- [ ] Tool output: `composer analyse` passes without the probes, `composer.lock` gains only `phpat/phpat`, the Deptrac configuration is unchanged, and `composer analyse:architecture` passes.
- [ ] Documentation check: coding-standards.md, architecture.md, and development.md state the rules in Documentation.

## Dependencies

- Blocked by: none.
