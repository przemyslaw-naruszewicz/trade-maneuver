---
id: SDLC-003
status: draft
---

# SDLC-003: Simpler process documents and cheaper delivery

## Goal

Each process rule is stated once, so a process change edits one or two files instead of five. Delivery gets cheaper: one commit per PR on `main`, shorter PR descriptions, a re-review after a small change that checks only that change, and review findings weighted by how often the problem occurs. Tasks get a lighter format and record their met AC on completion.

## Scope

In scope:

- [SDLC.md](../../SDLC.md), [AGENTS.md](../../AGENTS.md), [docs/agents/skills.md](../agents/skills.md), [docs/agents/independent-review.md](../agents/independent-review.md), and [docs/agents/issue-tracker.md](../agents/issue-tracker.md), changed to state the rules in Requirements.
- [docs/testing-strategy.md](../testing-strategy.md), for the test naming convention only.

Out of scope:

- Bringing the draft tasks to the new format. After this task merges, one separate task-change PR does that for TM-010 to TM-013 and every draft added in the old format until then.
- Ticking AC in done tasks. TM-001, TM-003 to TM-009, SDLC-001, and SDLC-002 stay as they are.
- Renaming existing tests. A later change of the naming convention is a separate task.
- Repository settings and GitHub protections. The owner changes them (SDLC §8).
- V2 review in GitHub Actions, an automatic fix loop, and CD.
- Updating the `mattpocock-skills` plugin from 1.3.1.
- Changes to CI, dependencies, code, or tests.
- Changes to `.claude/agents/independent-reviewer.md`. Its prompt of four items stays, because the reviewer finds the reviewed SHA for an incremental review on the PR itself.

## Requirements

### One home for each review rule

- SDLC §4 holds the review policy: the reviewer's inputs, the scope rule, task adaptation, `PASS` and `FAIL`, finding severity, the report format, incremental review, and the gates.
- `docs/agents/independent-review.md` is the reviewer's only procedure. It takes over the commands from the `code-review` section of `docs/agents/skills.md`: base and head SHA, the task-file diff, and the red-evidence check. It keeps the operational rules that no other file states: the test database only, literal `-e` options under zsh, red evidence on the main working tree, and memory notes that are neither requirements nor evidence. Its bullets that restate SDLC §4 become links to the relevant SDLC §4 sections.
- `docs/agents/skills.md` states only how each skill's steps change and links to SDLC for the rule. Its `code-review` section keeps the version pin, the upstream link, and one line saying that the skill runs only as the independent review, following `independent-review.md`. Its `pr` section names the skill sections that the SDLC PR description replaces.
- `docs/agents/issue-tracker.md` keeps the step mapping of `to-spec` and `to-tickets` and the working spec path. It drops the restated SDLC §3 and §4 rules: the ID format, the definition of ready, and planning PR naming.
- One-line pointers stay at the point of action for three guardrails: agents never merge, nothing changes after `FAIL` until the owner asks, and no other reviewer starts when the report is missing.

### AGENTS.md without duplicates

- The subagent limit appears once. That rule keeps the list of affected skills and the instruction to do their agent work one step after another in the main agent.
- The commit exceptions appear once. That rule keeps the push, the PR, and the ban on merging.
- The existing-tools rule keeps the implementer's checks. The reviewer's obligation (at least `MAJOR`, adapt the task) stays only in SDLC §4.
- The bullet that names Matt Pocock's `code-review`, the fictional code rule, the Docker commands, and the feedback rule stay as they are.

### Smaller SDLC corrections

- SDLC §2: the paragraph "An exception that changes scope or AC follows the same path …" goes. The paragraph on substantive changes gains "The owner's approval of a change to scope or AC does not replace a `PASS` result."
- The V2 review paragraph moves from the review flow in §4 to §8, next to the other planned stages.
- SDLC §6 sends readers to [docs/development.md](../development.md#ci-quality-checks) for the CI checks and keeps [README](../../README.md#quality-checks) for the pull-request check command.

### CI change verification covers environment configuration

- "Changes to CI configuration" in SDLC §6 applies to workflows, CI scripts, and environment configuration: `compose.yaml`, the files under `docker/`, `.dockerignore`, `Makefile`, `.env.example`, and the scripts in `bin/`. CI runs in the same containers and through the same Make targets, so a change to these files can break CI without touching a CI file.
- The owner's approval of changes to CI policies keeps its current scope.

### Lighter task format

- SDLC §3 and the `to-tickets` adaptation in `docs/agents/issue-tracker.md` change the task format:
  - A CI AC appears only when it asks for more than the merge gate, for example "no unexplained surviving mutants in pricing and sizing".
  - Each AC states its own verification method. A Verification section appears only for checks that no AC covers.
  - Dependencies list only "Blocked by".
  - AC are a checkbox list (`- [ ]`).
- The definition of ready and the merge gates stay. Each AC still names its test level and concrete expected values where SDLC §3 requires them.

### AC ticked on completion

- In the commit that sets the task to `done`, the implementing agent ticks (`- [x]`) each AC that the change meets. This happens before the review, so the same review covers it.
- SDLC §4 and §5 add ticked AC to the permitted task-file changes of an implementation PR. The reviewer reports an AC that is ticked but that it does not assess as `PASS`.
- The `implement` adaptation in `docs/agents/skills.md` states the step.

### Squash merge

- The owner merges every PR with GitHub "Squash and merge". A PR with one commit gives the same result, so the rule covers every PR.
- The squash commit title is the PR title, and its message is empty. The PR holds the detail.
- The source branch stays and keeps its commits. The PR's Commits tab and `refs/pull/<N>/head` keep them after merge, red commits included.
- SDLC §4 replaces "Merge commits keep them on `main`, so use `git bisect --first-parent` there" with the rule that red commits stay in the PR and in `refs/pull/<N>/head`. SDLC §5 names squash as the merge method.
- SDLC states the target repository settings: only squash merges allowed, squash commit title `PR_TITLE`, message `BLANK`, and branch deletion on merge off. The owner applies them.

### Finding severity weighs frequency

SDLC §4 defines the three levels:

- `BLOCKER`: on a normal path the change produces a wrong amount or order, loses or corrupts data, or weakens security, or the change breaks a merge gate or an AGENTS.md guardrail.
- `MAJOR`: a defect or a violation of a documented rule on a normal path; a rare edge case with one of the `BLOCKER` impacts; custom tooling that an installed tool could replace.
- `MINOR`: a rare edge case without those impacts, a judgement call such as a code smell, and wording or style.

A rare edge case is one that only a combination of conditions produces, conditions that normal use and the task's scenarios do not create. Each `BLOCKER` and `MAJOR` finding states its impact and whether it occurs on a normal path or only in a rare edge case, with the conditions that trigger it. Findings stay under their Standards or Spec axis. `BLOCKER` and `MAJOR` block `PASS`; `MINOR` does not.

### Shorter PR descriptions

- The rule applies to every PR description: implementation, planning, task-change, and change without a task.
- The visible part, outside `<details>`, has at most about 250 words.
- The description links the task and does not restate its requirements, its AC text, or the diff.
- Deviations and Risks list only real entries, otherwise "None".
- The AC, test, and red commit table and the rule-to-home table go inside `<details>`.
- An update after follow-up commits changes only the parts that no longer match the diff.

### Incremental review

- A `PASS` must still exist for the current `head SHA`.
- When an earlier SHA of the same PR has a `PASS` and the requirements have not changed since, the next review is incremental:
  - It reviews `git diff <reviewed-sha> <head-sha>` and how that change interacts with the rest of the PR. After an update from `main`, `git range-diff <old-base>..<reviewed-sha> <new-base>..<head-sha>` separates the PR's own changes from what came from `main`.
  - It reruns the red-evidence check only for AC whose red commit SHA or test assertions changed since the reviewed SHA.
  - The review scope (full or documentation) still follows the diff between base and head.
  - The report's result line adds `incremental since <short-sha>`.
- A full review is required when the requirements changed (the task, linked shared requirements, the requirements of a change without a task, or an approved task adaptation), when the PR has no earlier `PASS`, or when its last result was `FAIL`.
- Incremental reviews may follow one another. No full review is required before merge.
- An update from `main` gets an incremental review. SDLC §5 says so in its rule on updating the branch.
- The reviewer finds the reviewed SHA itself: it reads the latest independent review report posted on the PR, whose result line gives the result and the head SHA. SDLC §4 and `docs/agents/independent-review.md` add that report to the reviewer's inputs. The reviewer prompt keeps its four items (PR number, task ID or direct-change note, base SHA, head SHA), so the implementing agent passes no hint about where the review starts.
- The reviewer checks the conditions itself. When a condition fails, it runs a full review and says why in the report.
- The status-only exception stays as it is.
- The `code-review` adaptation in `docs/agents/skills.md` sets the skill's fixed point to the reviewed SHA and replaces its three-dot diff with `git range-diff` after an update from `main`.

### Test naming convention

`docs/testing-strategy.md` states the current convention as the rule:

- A PHPUnit test method is named `test_` plus a snake_case sentence that states the behavior in domain terms, for example `test_a_message_without_an_id_is_not_processable_and_audited`. Tests use no `#[Test]` attribute.
- A Behat `Feature` names the capability, for example "Trade signal intake". A `Scenario` is a declarative sentence that states the behavior, for example "A trade signal without a required field is rejected".

### Effect on this task's own PRs

- The rules take effect when this task's implementation PR merges. Until then, this task's PRs follow the rules on `main` at their base, with two exceptions for the implementation PR, by the owner's decision:
  - It ticks this task's met AC in the commit that sets `done`. Its review accepts that task-file change, and the task-file diff may contain only `approved` to `done` and the ticked AC.
  - Its review reads the rule-to-home table in the PR description and checks it against the diff, although a documentation review under the base rules reads nothing else from the PR description.

## Decisions

- On 2026-11-12 the owner accepted the consolidation of the review rules, the AGENTS.md deduplication, the lighter task format, and the SDLC §2 wording change. "Exception" there means a departure from the approved scope or AC found during implementation.
- The owner widened this task on 2026-11-13 with squash merge, severity that weighs frequency, shorter PR descriptions, incremental review, an explicit test naming convention, and AC ticked on completion. These go into one `SDLC-` task because they all change the process contract.
- Decided by the owner on 2026-11-13:
  - CI change verification covers environment configuration.
  - Squash commits carry only the PR title.
  - A rare edge case is at most `MINOR` unless it has one of the listed impacts.
  - The visible PR description has at most about 250 words.
  - No full review is required before merge.
  - The current test naming convention becomes the rule.
  - Done tasks stay unticked.
  - The draft tasks get the new format in a separate PR after this task.
  - The reviewer finds the reviewed SHA in its latest report on the PR, so the reviewer prompt and the subagent definition stay unchanged.
  - This task's implementation PR applies two exceptions to the base rules: ticked AC and the rule-to-home table check.
- Squash happens at merge in GitHub, not on the branch. A squash on the branch would change `head SHA`, invalidate the `PASS`, and destroy the red commits that the review checks.

## Constraints

- Every gate in SDLC §4 and §5 stays apart from the changes listed here. A `PASS` for the current `head SHA` stays required.
- Each moved rule keeps one statement in a document the implementing agent or the reviewer reads. A hard guardrail keeps a pointer at the point of action.
- The rule against implementation inventories in governing documents (AGENTS.md) applies.
- Follow the AGENTS.md document rules: update process documents in place and add no change log.

## Risks

- A chain of incremental reviews can miss a problem that only the whole diff shows. Each incremental review checks how its change interacts with the rest of the PR, and any change to the requirements forces a full review.
- A reviewer can label a real problem a rare edge case. The finding states its triggering conditions, and the owner sees them in the report.
- After squash merges, red commits are reachable only from the PR, not from a plain clone of `main`.

## Acceptance criteria

- [ ] Documentation check: SDLC §4 holds the review policy, `docs/agents/independent-review.md` holds the reviewer's procedure, including the commands moved from `docs/agents/skills.md` and the operational rules listed in Requirements, and `docs/agents/skills.md` and `docs/agents/issue-tracker.md` state only skill-step changes with links to SDLC. The three guardrail pointers stay at the point of action.
- [ ] Documentation check: AGENTS.md states the subagent limit, the commit exceptions, and the implementer's existing-tools checks once each, and the reviewer's obligation appears only in SDLC §4.
- [ ] Documentation check: SDLC §2 has the new sentence on owner approval and no "exception" paragraph, the V2 review paragraph is in §8, and SDLC §6 links `docs/development.md#ci-quality-checks` for the checks and README for the pull-request check command.
- [ ] Documentation check: SDLC §6 applies the CI change verification to the environment configuration files listed in Requirements, and the scope of owner approval for CI policy changes is unchanged.
- [ ] Documentation check: SDLC §3 and the `to-tickets` adaptation describe the lighter task format: a CI AC only beyond the gate, a verification method in each AC, a Verification section only for checks no AC covers, only "Blocked by", and AC as a checkbox list.
- [ ] Documentation check: SDLC §4, SDLC §5, and the `implement` adaptation let the implementing agent tick met AC in the commit that sets `done`, and the reviewer reports a ticked AC that it does not assess as `PASS`.
- [ ] Documentation check: SDLC §4 and §5 name squash merge for every PR, with the PR title as the commit title, an empty message, and red commits kept in the PR and `refs/pull/<N>/head`. The `git bisect --first-parent` sentence is gone, and SDLC lists the target repository settings as the owner's action.
- [ ] Documentation check: SDLC §4 defines `BLOCKER`, `MAJOR`, and `MINOR` as in Requirements, including the rare edge case rule and its exceptions, and requires each `BLOCKER` and `MAJOR` finding to state its impact and frequency.
- [ ] Documentation check: SDLC §4 and the `pr` adaptation limit the visible part of every PR description to about 250 words, with tables inside `<details>`, no restated task content, and "None" for empty Deviations and Risks.
- [ ] Documentation check: SDLC §4, SDLC §5, `docs/agents/independent-review.md`, and the `code-review` and `implement` adaptations define incremental review as in Requirements: its conditions, the reviewer finding the reviewed SHA in the latest report on the PR with an unchanged four-item prompt, `git range-diff` after an update from `main`, the red-evidence rerun only for changed AC, the full review cases, the fallback to a full review, and the report marker.
- [ ] Documentation check: `docs/testing-strategy.md` states the PHPUnit and Behat naming convention from Requirements, and no test file changes.
- [ ] Documentation check: the implementation PR description carries a rule-to-home table. It lists every removed or moved sentence that states a rule, with its new location or the reason it binds nothing, and every removed or renamed heading anchor with the links that used it. The reviewer checks the table against the diff.
- [ ] Manual check: the independent review of the implementation PR returns `PASS` as a documentation review, applying the two exceptions in "Effect on this task's own PRs", and the PR's task-file diff contains only `approved` to `done` and the ticked AC of this task.

## Dependencies

- Blocked by: none.
