# Coding standards

These rules apply to application-owned code, whoever writes it. [architecture.md](architecture.md) and [ddd.md](ddd.md) decide where code goes and how the domain is modeled.

## Readability

- Write code that a person can read easily, with intent carried by names and structure. Add a comment only when truly necessary, to record a reason, constraint, or trade-off the code cannot make clear.
- Use the [ubiquitous language](dictionary.md) in names, types, and control flow. Keep methods focused, use descriptive intermediate variables, and make control flow straightforward.
- Avoid obscure PHP-specific tricks and nested callback pipelines such as combinations of `array_map` and `array_walk`. Prefer explicit iteration or clearly named methods when they make intent easier to follow.

## PHP

- Target the project's PHP 8.5 runtime. Keep tooling and dependencies compatible with it, and verify installed versions before relying on language or package features. Review compatibility when upgrading to a newer stable release.
- Use modern PHP features where they make code clearer or improve type safety and correctness.
- Follow applicable PHP-FIG PSR standards for autoloading, coding style, and interoperability. Use standard PSR contracts where relevant and enforce the project's coding style through its configured formatter.
- Use braces for control structures, constructor property promotion where applicable, and TitleCase enum cases.

## Types and naming

- Apply the strictest practical typing: declare `strict_types=1` in application-owned PHP files and explicitly type parameters, return values, and properties, subject to inherited framework contracts.
- Use domain types, value objects, enums, and typed DTOs to avoid ambiguous primitives, unstructured arrays, and `mixed`. Add concise PHPDoc array shapes and collection element types where native PHP types cannot express the contract.
- Name application-owned interfaces with the `Interface` suffix, such as `OrderRepositoryInterface`, and data transfer objects with the exact `Dto` suffix, such as `SignalOutcomeDto`.
