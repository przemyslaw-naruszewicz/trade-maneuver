# Issue tracker: GitHub Issues with SDLC task drafts

Published tasks are GitHub Issues in this repository. Agents prepare tasks as Markdown drafts; a human approves and publishes them. [SDLC.md](../../SDLC.md) defines the process, task content, and gates. This file maps the skills that create or publish tasks onto it; [skills.md](skills.md) covers the others.

## Where work lives

| Stage | Location | Who changes it |
| --- | --- | --- |
| Working spec | `docs_internal/specs/<feature-slug>.md` (gitignored, so it stays out of the repository) | Agent drafts |
| Task | `docs/tasks/<TASK-ID>.md` | Agent drafts with `status: draft`; only a human sets `approved` |
| Published task | GitHub Issue | The person responsible for the task; source of requirements and status (SDLC §2) |

A human starts publication with `scripts/publish-issues.sh` (SDLC §3). Agents don't run it.

## GitHub operations

`gh` infers the repository from `git remote`. Agents only read:

- Read an Issue: `gh issue view <number> --json number,title,body,labels,comments,state`
- List Issues: `gh issue list --state open --json number,title,labels`
- When an Issue links a parent Issue, read the parent too; its shared requirements apply.

Agents don't create, edit, label, or close Issues, and don't publish tasks. Propose requirement changes to the user; the person responsible decides (SDLC §2). Comment on an Issue only when the user asks.

**When a skill says "publish to the issue tracker"**, write the local draft described for that skill below. Never run `gh issue create`.

**When a skill says "fetch the relevant ticket"**, read the published Issue with `gh issue view`. A file in `docs/tasks/` records preparation; it is not the current task.

## Skill adaptations

### to-spec

Write the spec to `docs_internal/specs/<feature-slug>.md`. The directory is gitignored, so the spec stays out of the repository (SDLC §2). Don't publish it or apply labels.

For step 2, start from the [seams in the testing strategy](../testing-strategy.md#seams), and propose a new kind of seam only when none fits.

### to-tickets

- Write one task per file at `docs/tasks/<TASK-ID>.md` (create `docs/tasks/` if missing) with the SDLC §3 frontmatter, `status: draft` and `github_issue: null`.
- Use the SDLC ID format, e.g. `AUTH-002`. If no existing task sets the prefix or next number, ask.
- Replace the skill's issue template with the task content SDLC §3 requires. Carry over every decision needed for implementation and verification, because the working spec never enters the repository (SDLC §2).
- Apply the SDLC §3 definition of ready to every AC, including its test level and concrete expected values.
- Express blocking edges as dependencies on other TASK-IDs or published Issue numbers. Put shared requirements in each task or in a linked parent Issue.
- The user approving the breakdown in step 4 is not task approval. Leave `status: draft` and apply no labels; `ready-for-agent` does not replace human approval.
- Don't edit tasks with `status: approved` or `published`.

### wayfinder, triage

Not used. Both create Issues or labels on GitHub. If invoked, tell the user that conflicts with SDLC §3 and stop.
