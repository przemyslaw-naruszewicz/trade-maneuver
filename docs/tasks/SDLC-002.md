---
id: SDLC-002
status: draft
---

# SDLC-002: Task adaptation found in review, a named review skill, and review scope by diff

## Goal

When the independent review finds that an approved task's own requirement leads to a worse solution, the owner can adapt the task in the same implementation PR instead of opening a separate task-change PR. The reviewer looks for custom tooling that an installed tool could replace. Every agent tool runs the independent review with the same, explicitly named skill, and a PR that changes only documentation gets a lighter review.

## Scope

In scope:

- [SDLC.md](../../SDLC.md) §2, §4 and §5, [docs/agents/skills.md](../agents/skills.md), [docs/agents/independent-review.md](../agents/independent-review.md), and [AGENTS.md](../../AGENTS.md), changed to state the rules in Requirements.

Out of scope:

- V2 review in GitHub Actions and an automatic fix loop.
- Changes to the review report format, the PR description, or the subagent limit from SDLC-001.
- Changes to CI, GitHub protections, or dependencies.

## Requirements

### Task adaptation found in review

- A review finding may show that the approved task itself causes the problem, for example a task that prescribes custom tooling an installed tool could replace. The reviewer then reports the finding with the recommendation "adapt the task" and the proposed change to the task's requirements or AC.
- The owner decides. Without the owner's explicit approval, the agent changes nothing, as after any `FAIL`.
- After approval, the implementing agent changes the task file in the same implementation PR, in its own commit `docs: <TASK-ID> adapt the task after review`, and then changes the code. The PR description lists the task change under Deviations, with a link to the owner's approval.
- The owner records the approval as a comment on the PR that names the proposed change.
- The next independent review assesses the adapted task at the head SHA. It checks that the task diff between base and head contains only the `approved` to `done` change and the approved adaptation. Any other task-file change still produces `FAIL`.
- A requirement change that the review did not find still goes through a separate task-change PR (SDLC §2).

### Custom tooling in review

- For every rule, script, tool configuration, or generic helper that the change adds, the reviewer checks whether an installed package or tool, or its configuration, does the same job. This includes tooling that the task prescribes.
- Such a finding is at least `MAJOR`. When the task prescribes the custom tooling, the finding carries the "adapt the task" recommendation.

### Named review skill

- The independent review uses the `code-review` skill from Matt Pocock's [mattpocock/skills](https://github.com/mattpocock/skills), with its Standards and Spec axes run one after the other. In Claude Code it is `mattpocock-skills:code-review`.
- Claude Code's built-in `code-review` skill, including `/code-review ultra`, is not used for the independent review.
- An agent tool that does not have the named skill follows the same steps from the skill's adaptation in `docs/agents/skills.md`, and the report names the tool and the skill used.
- Every mention of `code-review` in AGENTS.md, SDLC.md and `docs/agents/` refers to the named skill unambiguously.

### Review scope follows the diff

- One rule applies to every PR, whatever its task: the content of the diff between base and head sets the review scope.
- A PR whose diff changes only Markdown documentation, with no code, tests, configuration, dependencies, or CI files, gets a documentation review. The reviewer checks the AC of an implementation PR or the definition of ready of a planning PR, compares the task files, and checks consistency with AGENTS.md and the documents it makes mandatory. It skips the Standards axis for code, the red-evidence check, and test runs. The report keeps the SDLC §4 format and states that it is a documentation review.
- Every other PR gets the full independent review.
- CI and the `PASS` merge gate apply to both scopes.

## Decisions

- The owner asked for review-driven task adaptation and for an unambiguous review skill on 2026-11-06, after the review of PR #11 (TM-003) returned `PASS`. The PR added a custom PHPStan rule that TM-003 prescribed, and a spike showed that the installed `spaze/phpstan-disallowed-calls` (`disallowedMethodCalls` with `allowInInstanceOf`) reports the same calls through configuration. The reviewer did not report it.
- Adaptation happens in the same PR, not in a separate task-change PR, so that a finding does not stop delivery for a second planning round. The owner chose this over keeping a separate task-change PR for review findings on 2026-11-06.
- A full review of a documentation-only PR runs nothing beyond reading, so most of its cost buys no extra evidence. Process documents steer every agent, so a documentation review stays instead of no review at all. The owner chose this scope rule over exempting planning or `SDLC-` PRs on 2026-11-06, to keep one rule for all tasks.
- Claude Code offers two skills named `code-review`: the built-in one and Matt Pocock's. The independent review uses Matt Pocock's (owner decision, 2026-11-06).

## Constraints

- The rules keep every other SDLC §4 and §5 gate. An adapted task still needs a new independent review with `PASS` for the current head SHA.
- The scope rule depends only on the diff, never on the task prefix or PR type.
- Follow the AGENTS.md rules on documents: update process documents in place and add no change log.

## Acceptance criteria

- [ ] Documentation check: SDLC §2 and §4 allow the task adaptation found in review, with the owner's approval as a PR comment, its own commit, the Deviations entry, and a separate task-change PR for every other requirement change.
- [ ] Documentation check: SDLC §4 (independent review) and §5 (merge gates) permit the approved adaptation in the task-file comparison and still fail any other task-file change.
- [ ] Documentation check: `docs/agents/independent-review.md` and the `code-review` adaptation in `docs/agents/skills.md` require the custom-tooling check as at least `MAJOR`, including tooling the task prescribes, and the "adapt the task" recommendation.
- [ ] Documentation check: `docs/agents/independent-review.md`, `docs/agents/skills.md`, AGENTS.md and SDLC.md name Matt Pocock's `code-review` skill (`mattpocock-skills:code-review` in Claude Code) for the independent review and exclude the built-in Claude Code `code-review`.
- [ ] Documentation check: SDLC §4 and `docs/agents/independent-review.md` define the review scope by diff: a documentation review for a diff that changes only Markdown documentation, the full review otherwise, with CI and the `PASS` gate for both.
- [ ] Manual check: the independent review of this task's implementation PR reports which skill it used and that it ran as a documentation review.

## Dependencies

- Blocked by: none.
- Unblocks: none.

## Verification

- Review the changed documents against Requirements.
- The implementation PR's own independent review serves as the manual check.
