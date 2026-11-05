---
id: SDLC-001
status: draft
---

# SDLC-001: Agent-started review and shorter feedback

## Goal

Cut what the owner has to read during delivery, and remove the manual steps between implementation and review. The implementing agent starts the independent review itself, the reviewer posts a short report to the PR, and the owner gets the result with one recommended action. Task IDs mark commits, PR titles, and branches, and agents check for existing tools before writing their own.

## Scope

In scope:

- [SDLC.md](../../SDLC.md) §3 and §4, [AGENTS.md](../../AGENTS.md), [docs/agents/skills.md](../agents/skills.md), and [docs/agents/issue-tracker.md](../agents/issue-tracker.md), changed to state the rules in Requirements.
- A new vendor-neutral reviewer instruction, `docs/agents/independent-review.md`.
- A Claude Code subagent definition, `.claude/agents/independent-reviewer.md`, that points to that instruction.

Out of scope:

- V2 review in GitHub Actions, which stays planned in SDLC §4.
- Cross-vendor review, such as Claude implementing and Codex reviewing, and any configuration for choosing the reviewer.
- An automatic fix loop.
- Changes to CI, GitHub protections, or dependencies.

## Requirements

### Review report

The reviewer posts one PR comment. Its visible part has at most 10 lines:

```
## Independent review: PASS|FAIL
<TASK-ID> · base <short-sha> · head <short-sha> · task files <OK|issue> · reviewer: <tool> <model>
AC <passed>/<assessed> PASS · red evidence <ok>/<checked> OK
- <BLOCKER|MAJOR> <file:line>: <one-line reason>
- AC <n> FAIL (<defect|missing evidence>): <one-line reason>
Recommendation: <one action>
```

A collapsed `<details>` section holds everything else SDLC §4 requires: the AC table with text, result, and evidence for each AC; the red-evidence results; `MINOR` findings; and the task-file comparison. A change without a task shows the direct-change note in place of the task ID.

### Pull request description

An implementation PR description contains, in this order: the task ID with a link to the task file; a Summary of at most five bullets; a table of AC, test, and red commit SHA; Deviations; and Risks. Command output appears only where an AC requires the PR to show it, inside `<details>`.

### Feedback to the owner

Agents report the outcome and what needs the owner's decision, in at most five lines unless the owner asks for more. After a review, the message gives the result, the number of `BLOCKER` and `MAJOR` findings, the reviewer's recommendation, and the PR link.

### Review flow

- Invoking `implement` on an approved task, or `to-tickets`, authorizes the agent to push the branch, open the PR, and start the independent review. Agents still never merge.
- `implement` runs the pull-request checks from README before pushing. It no longer ends with a self-review.
- The implementing agent starts exactly one reviewer subagent of its own tool, with a fresh context. Its prompt contains only the PR number, the task ID or the direct-change note, the base SHA, and the head SHA.
- The reviewer follows `docs/agents/independent-review.md`, posts its report with `gh pr comment`, and returns the result and recommendation. The implementing agent relays them without editing.
- After the reviewer returns, the implementing agent checks that the checked-out ref is unchanged and `git status --short` is empty. A difference makes the report invalid, and the agent tells the owner.
- After `FAIL`, the agent changes nothing until the owner asks. After every change to the head SHA that the owner asks for, including an update from `main`, the agent starts a new review without asking again.
- When the reviewer cannot run or does not return a report, for example because of an exhausted quota or a tool error, the agent stops and tells the owner in one line. It does not switch to another reviewer. A missing report still blocks merge.
- An independent review that the owner starts in a separate session remains valid.

### Subagent limit

The only permitted subagent is the reviewer. At most one runs at a time, and it starts no agents of its own. Where a skill runs parallel agents, such as the two axes of `code-review`, the reviewer runs them one after the other. Every other rule against subagents stays.

### Reviewer rules in `docs/agents/independent-review.md`

- Do not edit, create, or delete repository files. Do not commit or push.
- Read AGENTS.md and the documents it makes mandatory, and the approved task from the base SHA, as `docs/agents/skills.md` already describes for the independent review.
- Memory notes of any agent tool are neither requirements nor evidence. Use only the task at the base SHA, repository documents, and what the reviewer verifies.
- Check red evidence on the main working tree, because the Docker Compose container mounts it, and return to the starting ref with an empty `git status --short`.
- Write the report in the format above, with one recommended action.

### Claude Code subagent definition

`.claude/agents/independent-reviewer.md` sets `omitClaudeMd: true`, gives no Edit or Write tools, and tells the reviewer to follow `docs/agents/independent-review.md`.

### Status-only change after review

A `PASS` stays valid when the only changes after the reviewed head SHA set `status: draft` to `approved` in tasks that review assessed. The agent checks this with `git diff <reviewed-sha> HEAD` and adds one line to the PR: "Since `<sha>` only task status changed; PASS holds." Any other change invalidates the result as before. This replaces the rule in SDLC §3 step 3 that the review that counts runs after `approved` is set.

### Task IDs in names

- Commits on a task branch and the PR title use `<type>: <TASK-ID> <description>`, for example `test: TM-003 reject a trade signal without an instrument`. Automatic merge commits are exempt.
- Task branches are named `<type>/<task-id>-<short-description>` in lowercase, for example `feat/tm-003-signal-intake`.
- A planning PR and its commits name every task they add, as a range for consecutive IDs (`TM-007..TM-010`) or a list. A task-change PR names the changed task. A change without a task carries no ID.

### Existing tools first

Before writing tooling, scripts, infrastructure code, or generic helpers that a task does not specify, the agent checks in this order:

1. what is already installed;
2. which packages or tools do the job;
3. how the best candidates compare, testing the leading one in a throwaway spike outside the PR.

The agent then presents the option and its cost to the owner; a new dependency needs the owner's approval. Domain logic is exempt. A task that prescribes custom tooling states why no existing tool fits, and the reviewer reports custom tooling that an existing tool could replace as a finding.

## Decisions

- The owner decided these rules on 2026-11-05, in a discussion of the review overhead on PR #7. Its review report had about 4,300 characters and its description about 11,000.
- Implementation and review use the same tool. The owner may revisit cross-vendor review later.
- A dry run on 2026-11-05 reviewed one AC of PR #7 (base `0c0ef6e`, head `28977b9`) with a Claude Code subagent. It produced the report format above, checked red commit `7d5d097` on an assertion, and returned to the starting ref with a clean working tree. It did not see the parent conversation, but it did receive the auto-memory index together with CLAUDE.md. A probe with `omitClaudeMd: true` removed both, which is why the reviewer reads AGENTS.md itself.
- The owner confirmed on 2026-11-05 that Codex can start a subagent and declines only because of the current AGENTS.md rule. No Codex dry run is required.
- The report keeps a collapsed details section, so the owner can check what a `PASS` rests on without reading it by default.

## Constraints

- The rules keep every SDLC §4 and §5 gate. Only the report layout, the review trigger, and the status-only exception change.
- Follow the AGENTS.md rule on documents: update process documents in place and add no change log.

## Acceptance criteria

- [ ] Documentation check: SDLC §4 defines the review report, its visible block, and its details section as in Requirements.
- [ ] Documentation check: SDLC §4 and the `pr` adaptation in `docs/agents/skills.md` define the PR description as in Requirements.
- [ ] Documentation check: AGENTS.md states the feedback rule, the subagent limit, and the existing-tools rule as in Requirements.
- [ ] Documentation check: SDLC §4 and `docs/agents/skills.md` describe the review flow as in Requirements, including the authorization for `implement` and `to-tickets`, the removed self-review, the post-check of the working tree, and the unavailable-reviewer rule.
- [ ] Documentation check: SDLC §3 and §4 state the status-only exception, and SDLC §3 step 3 no longer requires a new review after `approved` alone.
- [ ] Documentation check: AGENTS.md and SDLC §4 state the task ID rule for commits, PR titles, and branches, and `docs/agents/issue-tracker.md` applies it to `to-tickets` together with the existing-tools rule for tasks.
- [ ] Documentation check: `docs/agents/independent-review.md` contains the reviewer rules, and `.claude/agents/independent-reviewer.md` sets `omitClaudeMd: true`, gives no Edit or Write tools, and points to it.
- [ ] Manual check: the implementing agent starts the reviewer for this task's implementation PR, the reviewer posts its report in the new format, and the agent reports the post-check result.

## Dependencies

- Blocked by: none.
- Unblocks: none.

## Verification

- Review the changed documents against Requirements.
- The implementation PR's own independent review runs through the new flow and serves as the manual check.
