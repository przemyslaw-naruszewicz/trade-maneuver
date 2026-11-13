# ADR-0005: Money and exact arithmetic with brick/money and brick/math

Date: 2026-11-06

Status: Accepted

## Context

[architecture.md](../architecture.md#money-and-exact-arithmetic) forbids `float` for monetary values and requires a Money library chosen in an ADR. That ADR must give the precision, scale, rounding mode and rounding boundaries for each operation, and state how values are persisted and serialized.

The investment configuration (TM-003) is the first code with exact values: the order amount, which is Money, and the limit price tolerance, an exact decimal percentage. Later tasks compute a limit price from a reference price and an order quantity from the order amount. The project owner chose `brick/money` with `brick/math` on 2026-10-31.

The installed versions are `brick/money` 0.15.2 and `brick/math` 0.20.0. Both declare `php: ^8.2`. A smoke run under PHP 8.5.10 with every error level enabled raised no deprecation, and the project's test suite exercises them on the same runtime. `brick/money` 0.15 does not accept `brick/math` 1.0, so `brick/math` stays on 0.20.

## Decision

Use `Brick\Money\Money` for every money amount and `Brick\Math\BigDecimal` for exact decimals that are not money, such as percentages. Domain value objects wrap them; the rest of the code does not create them.

Deptrac allows the `Brick\Money` and `Brick\Math` namespaces in `Domain` as a separate `MoneyLibrary` layer. `Application` stays limited to `Domain`; it passes decimal strings to domain factories and reads strings back.

### Input

- External decimal values arrive as plain decimal strings matching `^-?\d+(\.\d+)?$`. Brick also parses exponents (`2e2`) and fractions (`400/2`); a domain value object checks the plain form before calling Brick and rejects anything else.
- No input is rounded. A value with more decimal places than its scale allows is rejected (`RoundingMode::Unnecessary`), so a typo is never silently corrected.

### Precision, scale and rounding per operation

| Operation | Scale | Rounding |
| --- | --- | --- |
| Order amount | The currency's default fraction digits (`DefaultContext`): 2 for EUR | None; more decimal places are rejected |
| Limit price tolerance | 2 decimal places of a percentage, from 0 % to 5 % inclusive | None; more decimal places are rejected |
| Comparisons and currency checks | Exact | None |
| Limit price from reference price and tolerance, for a buy | The quote currency's fraction digits | `RoundingMode::Down`, so the limit price does not exceed the tolerance and stays at or above the reference price |
| Order quantity from order amount and limit price | Whole units | `RoundingMode::Down` on the exact quotient, so the cost never exceeds the order amount |
| Allocation and currency conversion | Not used | Not used: an investment configuration has one currency and does not convert |

Intermediate results keep full precision: `BigDecimal` multiplication is exact, and division names its scale and rounding mode at the call site. Rounding happens only at the boundaries in the table.

### Persistence and serialization

- PostgreSQL stores amounts in `numeric(p, s)` columns whose scale matches the value object: `numeric(19, 2)` for EUR order amounts and limit prices, and `numeric(5, 2)` for the tolerance. The currency is stored in its own `char(3)` column.
- PDO returns `numeric` as a string. Eloquent models declare no numeric cast, and repositories pass the string straight to the value object. Laravel's `decimal:` cast and `float` casts are not used.
- JSON, audit records and messages carry amounts as decimal strings at their scale, such as `"200.00"`, never as JSON numbers.

## Alternatives considered

- `moneyphp/money` keeps amounts as integer minor units in strings and has strong currency support. Its arithmetic API is narrower for non-money decimals such as percentages, and the owner chose Brick.
- Integer minor units without a library avoid a dependency, but percentages, prices with more decimals than the currency, and exact division would need hand-written arithmetic.
- `bcmath` directly is exact but untyped: every value is a string, and scale and rounding are easy to get wrong at each call.

## Consequences and trade-offs

- Calculations are exact, and the place where rounding happens is visible in code and in this record.
- Brick throws its own exceptions. Domain value objects translate them into domain exceptions with clear messages; a Brick exception that escapes means a missing check.
- `numeric(19, 2)` fits currencies with two fraction digits. A currency with another scale needs a migration and a review of this record.
- Brick 0.x releases may break the API. Upgrades need the test suite, and a move to `brick/math` 1.0 waits for a compatible `brick/money` release.
