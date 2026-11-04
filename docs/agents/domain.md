# Domain docs

Single context. Several skills name `GLOSSARY.md` and `docs/adr/`; in this repository they mean:

| Skills say | This repository |
| --- | --- |
| `GLOSSARY.md` | [`docs/dictionary.md`](../dictionary.md) |
| `docs/adr/`, `0001-slug.md` | [`docs/ADR/`](../ADR/), `adr-0001-slug.md` |

Never create `GLOSSARY.md`, `GLOSSARY-MAP.md`, or `docs/adr/`. This applies to `domain-modeling`, `grill-with-docs`, `improve-codebase-architecture`, `tdd`, `diagnosing-bugs`, `pr`, `wait-what`, and `codebase-design`.

## Before exploring

Read `docs/dictionary.md` and the ADRs in `docs/ADR/` that touch the area. Check each ADR's `Status:` line: an accepted ADR can describe a target that is not implemented yet. The architecture and DDD rules listed in AGENTS.md also apply.

## Dictionary

- Keep its table format and follow the rules in its introduction and "Using and extending the dictionary" section. Don't apply the skill's `GLOSSARY-FORMAT.md` or add columns or sections.
- `domain-modeling` updates the glossary as soon as a term is resolved. Here a term is resolved only when the project owner agrees on its meaning. Until then, raise it as an open question for the owner and leave the dictionary unchanged.
- When a concept you need is missing, ask the owner. Don't invent a term.

## ADRs

- Follow the ADR rules in [architecture.md](../architecture.md#architecture-decision-records) and the structure of the existing ADRs (`# ADR-NNNN: <title>`, `Date:`, `Status:`, Context, Decision, Alternatives considered, Consequences and trade-offs), not the skill's `ADR-FORMAT.md`. Number a new ADR as the highest in `docs/ADR/` plus one.
- Offer an ADR when `domain-modeling`'s three criteria hold, or when architecture.md or ddd.md require one.
- Write or change an ADR only when the user asks. Give a new ADR `Status: Proposed`.
- Acceptance and every other status change (accepted, superseded) belong to the owner. Make them only on the owner's explicit instruction; the owner may also make them directly.
- Don't rewrite an accepted ADR; supersede it as architecture.md describes.

## Use the dictionary's vocabulary

Use dictionary terms when naming domain concepts in task titles, Issues, test names, PR text, and refactor proposals. Don't drift to synonyms.

## Flag ADR conflicts

If your output contradicts an ADR, say so explicitly rather than silently overriding it:

> _Contradicts ADR-0001 (domain independence from Eloquent), but worth reopening because…_
