# ADR-0006: Trade signal contract validation through domain value objects, and PSR-3 logging in the application layer

Date: 2026-11-07

Status: Accepted

## Context

TM-004 adds the intake of `tm.trade-signal.v1` messages. The use case receives the raw message body, so it validates the contract itself and reports each violation as a field path and a message in `SignalRejected`. [ddd.md](../ddd.md#placement-of-business-behavior) requires domain value objects to refuse invalid values even when no framework validator runs, so the values are checked in the domain whatever else checks them.

The use case also has to log the messages it sets aside, such as a conflicting duplicate, with a warning. Deptrac allowed `Application` to depend only on `Domain` and the PSR-20 clock contract.

The project owner decided both questions on 2026-11-07, when TM-004 was implemented.

## Decision

Validate the contract with the domain value objects of the trade signal and a translator in `Application`. The translator reads fields by path, checks presence and JSON types, passes each value to its value object, and turns a refusal into a `{field, message}` violation. Decimal values reach Brick as strings and are never read as floats. Fields Trade Maneuver does not interpret, such as `extensions`, are only checked for their structure; the inbox keeps the body exactly as delivered.

Log through the PSR-3 contract, `Psr\Log\LoggerInterface`. `psr/log` becomes a direct dependency, and Deptrac allows the `Psr\Log` namespace in `Application`, like `Psr\Clock`. Laravel binds the interface to its logger.

## Alternatives considered

- JSON Schema, for example `justinrainbow/json-schema`, now installed only as a development dependency of Infection. A schema would be a machine-readable contract for producers, but it cannot compare decimal strings (`expectedMove.min ≤ max`, `confidence` from 0 to 1), because `minimum` and `maximum` apply only to JSON numbers, which PHP reads as floats. It cannot check the investment configuration or the authorization either. The value objects would still validate, so the schema would duplicate the structural checks and add a production dependency. It may come later as the published contract, with AsyncAPI and contract tests.
- Laravel Validator, or `nette/schema`, in an infrastructure adapter behind a port. Both validate structure and formats, but their numeric comparisons convert through float, and the value objects would still repeat the value rules.
- A project logging port with a Laravel adapter. It avoids the dependency, but replaces a standard PSR contract with project code.

## Consequences

- Each value rule exists once, in its value object, and the domain refuses invalid values without a framework.
- The translator is project code that a schema library could partly replace. Its tests list every violation with its field path.
- Within one composite field, such as `referencePrice`, only the first invalid part is reported.
- `Application` may use PSR-3. A new package still needs a task that allows it.
