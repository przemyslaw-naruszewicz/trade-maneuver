# ADR-0003: Use RabbitMQ for integration messaging

Date: 2026-10-27

Status: Accepted as the target design; implementation is pending.

## Context

Trade Maneuver will receive signals from independent analytics applications and return execution
results. It owns the versioned integration contract. Producers must be able to publish while
Trade Maneuver is unavailable, and retries must not cause repeated execution.

The transport needs durable buffering, acknowledgements, failed-message handling, and separate
permissions for each producer. It must fit the constrained development environment described in
[dev-infrastructure.md](../dev-infrastructure.md). The current Laravel setup uses PostgreSQL for
background jobs; external integration messaging has not been implemented.

## Decision

Use RabbitMQ as the message broker for signal intake and execution results:

- Producers publish persistent `tm.trade-signal.v1` messages to the `tm.trade-signals` exchange
  using publisher confirms. An outbox entry is marked as sent only after confirmation.
- Trade Maneuver consumes from a durable queue and acknowledges only after committing to its
  database. Messages it cannot process are routed to a dead letter exchange.
- Results are published to `tm.trade-results`, with a separate queue for each producer.
- Each producer has its own credentials and restricted permissions for publication and result
  consumption. Producers and Trade Maneuver do not access each other's databases.
- Delivery is at least once. Producer outboxes, receiver deduplication, and recoverable processing
  remain necessary; a publisher confirmation does not establish business completion.

RabbitMQ is selected because it provides durable transport buffering, redelivery, dead-letter
routing, and producer-specific permissions in one service. AMQP clients are available in widely
used programming languages. Producers still own their outboxes and retries to RabbitMQ.

This decision covers external integration messaging. It does not replace Laravel's PostgreSQL
background-job queue or select a trading broker (execution provider).

## Alternatives considered

| Criterion | HTTP API | Redis/Valkey Streams | RabbitMQ |
|---|---|---|---|
| Channel | `POST /trade-signals` | A stream per producer, a Trade Maneuver consumer group | `tm.trade-signals` exchange, a durable Trade Maneuver queue |
| Buffer during Trade Maneuver downtime | Each producer's outbox | Redis/Valkey | RabbitMQ |
| Validation | Immediate (400, 422) | After consumption, through the result channel | After consumption, through the result channel |
| Retry responsibility | Each producer | Producer to Redis/Valkey, then consumer | Producer to RabbitMQ, then message broker |
| Failed messages | Producer's `failed` queue | A separately implemented failure stream | Built-in dead letter exchange |
| Producer permissions | API token | Account with `XADD` permission on its key | User with write permission to the signal exchange |
| Additional service | None | Redis or Valkey | RabbitMQ |
| Required client | HTTP | Streams | AMQP |

### HTTP API

Trade Maneuver would store the signal and commit before returning `202 Accepted`. A duplicate
would receive the same 2xx response as the original. The producer would retry on 429, 503,
other 5xx responses, timeouts, and broken connections, respecting `Retry-After`. Responses
400, 401, 403, and 422 would move the entry to `failed` with an alert. Deduplication by `id`
would resolve uncertain delivery after a timeout.

HTTP avoids an additional infrastructure service, but buffering and reliable delivery depend
on every producer's implementation. Faulty retries can lose signals without Trade Maneuver
knowing. RabbitMQ provides a shared transport buffer and message-delivery controls.

An HTTP gateway remains a possible future extension for producers outside the private network.
It would publish to the same RabbitMQ exchange; it is not part of the selected initial transport.

### Redis/Valkey Streams

Producers would use `XADD` to publish to `tm:signals:<source>`. Trade Maneuver would use a consumer
group (`XREADGROUP`), acknowledge with `XACK`, and reclaim messages from failed consumers with
`XAUTOCLAIM`. Pub/Sub loses messages without a subscriber, while lists lack acknowledgements.

The evaluated setup requires `appendfsync always` rather than `everysec`, which can lose up to
a second of writes in a crash; `maxmemory` with `noeviction`; retention and `MAXLEN` trimming
rules; and a container memory limit. Applicable Redis/Valkey licensing would need verification
before adoption.

Streams can buffer signals, but failed-message handling and recovery require more custom
implementation. RabbitMQ offers those transport mechanisms directly.

### A producer's PostgreSQL journal as the integration interface

Trade Maneuver would need database connections to every producer, and each producer would need
PostgreSQL. This violates service data ownership and couples the integration to producer storage.
A journal may serve as a private outbox, but it is not the integration interface.

### Kafka

Kafka adds resource and operational costs that are not justified for the target development
profile. It is not selected for this integration.

## Consequences and trade-offs

- RabbitMQ becomes an additional service to deploy, monitor, and recover. Configure an explicit
  container memory limit and an absolute RabbitMQ memory threshold based on measurements of the
  full environment.
- Persistent broker storage, failed-message recovery, and retention need operational configuration.
  A single development instance does not provide high availability.
- Keep broker access private where possible and restrict each producer's permissions.
  Connections outside trusted networks require TLS.
- An AMQP client and the corresponding application adapters still need selection and implementation.
  RabbitMQ types must remain outside the domain and application core.
- Transport acceptance, durable receiver acceptance, and execution completion remain distinct.
  Tests must cover duplicate delivery, lost acknowledgements, worker restarts, downtime, and
  recovery without repeated business effects.

The public contract and producer requirements remain in [integration.md](../integration.md).
Transaction and delivery guarantees follow the
[architecture's reliable-messaging rules](../architecture.md#idempotency-and-reliable-messaging)
and the [testing strategy](../testing-strategy.md).
