# Domain dictionary

This document records the domain's ubiquitous language: business terms and meanings agreed with the project owner and used consistently in conversations, code, tests, APIs, events, and documentation. It guides terminology in the [architecture](architecture.md) and [DDD rules](ddd.md). The initial terms come from the documented service purpose and scenarios; business contexts and aggregates still need modeling.

Add entries for agreed business concepts. Keep definitions of architectural patterns, DDD modeling concepts, transport protocols, and implementation tools in the relevant technical documentation. For example, Open Host Service (OHS), Published Language (PL), and Anti-corruption layer (ACL) are described in [integration.md](integration.md#2-ddd-relationship).

## Vocabulary

| Term | Meaning in this project |
| --- | --- |
| Analytics application | The external application that owns analytical decision-making and sends execution instructions to this service. |
| Execution service | This microservice. It validates instructions against its domain rules, coordinates their execution, and exposes their status and outcomes. |
| Execution instruction | A request from the analytics application to perform execution work. Receipt alone does not establish business validity or successful completion. |
| Execution | The work performed in response to an execution instruction. Its business effects must not be repeated because of redelivery or retries. |
| Automatic execution mode | The target operating mode: instruction processing and order submission to the execution provider proceed without human approval. |
| Manual execution mode | A reserve operating mode requiring human approval before order submission to the execution provider. It provides control during initial rollout and remains available as a fallback in emergencies. |
| Execution provider | An external system used to carry out execution work. Its integration contract defines how requests are accepted and how outcomes can be confirmed. No specific provider is selected by this term. |
| Execution status | Information about the progress of an execution. Durable acceptance and business completion are distinct; exact lifecycle states remain to be defined from business scenarios. |
| Execution result | The recorded outcome of processing an execution instruction. A completed duplicate instruction returns its stored result under the agreed idempotency contract. |
| Business rejection | A refusal to perform a requested action because it violates a domain rule or precondition. A terminal rejection retains its outcome for duplicate handling. |
| Recoverable failure | A processing failure for which the workflow defines a retry or recovery path. An uncertain external outcome must be reconciled before an irreversible action is retried. |
| Money | An exact amount together with its currency. Precision, scale, and rounding rules are explicit; calculations and representations must not pass through `float`. |

## Using and extending the dictionary

- Use these terms consistently in conversations, code, tests, APIs, events, and documentation. Translate external vocabulary at integration boundaries when its meaning differs.
- Add or revise entries when an agreed business concept is introduced or clarified. Keep definitions consistent with the architecture, DDD rules, and accepted ADRs.
- Resolve ambiguous meanings with the project owner. Examples such as `Order` and reservation limits do not establish approved aggregates or business rules. Instruction payloads, lifecycle states, and internal context boundaries remain open until modeled from concrete use cases.
