# Issue tracker: Markdown task backlog

The authoritative task backlog is `docs/tasks/` in this repository. The project does not use GitHub Issues. Agents prepare tasks as Markdown drafts; a human approves them in a planning PR. [SDLC.md](../../SDLC.md) defines the process, task content, and gates. This file maps the skills that create tasks onto it; [skills.md](skills.md) covers the others.

## Where work lives

| Stage | Location | Who changes it |
| --- | --- | --- |
| Working spec | `docs_internal/specs/<feature-slug>.md` (gitignored, so it stays out of the repository) | Agent drafts |
| Draft task | `docs/tasks/<TASK-ID>.md` with `status: draft` | Agent drafts on a planning branch |
| Approved task | The same file on `main` with `status: approved` | Only a human sets `approved`; later changes go through a task-change PR (SDLC §2-3), or through an owner-approved adaptation found in review (SDLC §4) |
| Completed task | The same file on `main` with `status: done` | The implementation PR sets `done`, which takes effect on merge (SDLC §4) |

## Issue tracker operations

Propose requirement changes to the user; the owner decides (SDLC §2).

**When a skill says "publish to the issue tracker"**, write the local draft described for that skill below.

**When a skill says "fetch the relevant ticket"**, run `git fetch origin` and read the approved task with `git show origin/main:docs/tasks/<TASK-ID>.md`. An independent review reads it from the base SHA instead, or from the head SHA after an adaptation the owner approved ([skills.md](skills.md#code-review)).

## Skill adaptations

### to-spec

Write the spec to `docs_internal/specs/<feature-slug>.md`. The directory is gitignored, so the spec stays out of the repository (SDLC §2). Don't publish it or apply labels.

For step 2, start from the [seams in the testing strategy](../testing-strategy.md#seams), and propose a new kind of seam only when none fits.

### to-tickets

- Write one task per file at `docs/tasks/<TASK-ID>.md` (create `docs/tasks/` if missing), with the ID, frontmatter, and `status: draft` as [SDLC §3](../../SDLC.md#3-task-preparation-and-approval) describes, in place of the skill's `.scratch/` files and their `NN` numbers.
- Replace the skill's ticket template with the SDLC §3 task content and format: AC as a checkbox list, each with its verification method; a Verification section only for checks no AC covers; an AC on CI only when it asks for more than the merge gate; and the skill's blocking edges as "Blocked by" task IDs. Carry over every decision needed for implementation and verification, because the working spec never enters the repository (SDLC §2).
- The user approving the breakdown in step 4 is not task approval. Leave `status: draft` and apply no labels; `ready-for-agent` does not replace human approval.
- A task that prescribes custom tooling, scripts, infrastructure code, or generic helpers states why no existing tool fits (AGENTS.md).
- Name the planning PR and its commits as [SDLC §4](../../SDLC.md#branches-and-prs) describes, then push, open the PR, and start the review under the [SDLC §4 review flow](../../SDLC.md#review-flow). Never merge.
- Leave tasks with `status: approved` or `done` as they are. Propose a task-change PR for an approved task; a fix or extension of a `done` task gets a new task linked to it.

### wayfinder, triage

Not used. Both create Issues or labels on GitHub. If invoked, tell the user that conflicts with SDLC §3 and stop.
