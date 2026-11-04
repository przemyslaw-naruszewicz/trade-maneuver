# Issue tracker: Markdown task backlog

The authoritative task backlog is `docs/tasks/` in this repository. The project does not use GitHub Issues. Agents prepare tasks as Markdown drafts; a human approves them in a planning PR. [SDLC.md](../../SDLC.md) defines the process, task content, and gates. This file maps the skills that create tasks onto it; [skills.md](skills.md) covers the others.

## Where work lives

| Stage | Location | Who changes it |
| --- | --- | --- |
| Working spec | `docs_internal/specs/<feature-slug>.md` (gitignored, so it stays out of the repository) | Agent drafts |
| Draft task | `docs/tasks/<TASK-ID>.md` with `status: draft` | Agent drafts on a planning branch |
| Approved task | The same file on `main` with `status: approved` | Only a human sets `approved`; later changes go through a task-change PR (SDLC §2-3) |
| Completed task | The same file on `main` with `status: done` | The implementation PR sets `done`, which takes effect on merge (SDLC §4) |

## Issue tracker operations

Propose requirement changes to the user; the owner decides (SDLC §2).

**When a skill says "publish to the issue tracker"**, write the local draft described for that skill below.

**When a skill says "fetch the relevant ticket"**, run `git fetch origin` and read the approved task with `git show origin/main:docs/tasks/<TASK-ID>.md`. An independent review reads it from the base SHA instead ([skills.md](skills.md#code-review)).

## Skill adaptations

### to-spec

Write the spec to `docs_internal/specs/<feature-slug>.md`. The directory is gitignored, so the spec stays out of the repository (SDLC §2). Don't publish it or apply labels.

For step 2, start from the [seams in the testing strategy](../testing-strategy.md#seams), and propose a new kind of seam only when none fits.

### to-tickets

- Write one task per file at `docs/tasks/<TASK-ID>.md` (create `docs/tasks/` if missing) with the SDLC §3 frontmatter and `status: draft`.
- Use the SDLC ID format, e.g. `AUTH-002`. If no existing task sets the prefix or next number, ask.
- Replace the skill's issue template with the task content SDLC §3 requires. Carry over every decision needed for implementation and verification, because the working spec never enters the repository (SDLC §2).
- Apply the SDLC §3 definition of ready to every AC, including its test level and concrete expected values.
- Express blocking edges as dependencies on other TASK-IDs. Put shared requirements in each task or in a linked repository document.
- The user approving the breakdown in step 4 is not task approval. Leave `status: draft` and apply no labels; `ready-for-agent` does not replace human approval.
- Leave tasks with `status: approved` or `done` as they are. Propose a task-change PR for an approved task; a fix or extension of a `done` task gets a new task linked to it.

### wayfinder, triage

Not used. Both create Issues or labels on GitHub. If invoked, tell the user that conflicts with SDLC §3 and stop.
