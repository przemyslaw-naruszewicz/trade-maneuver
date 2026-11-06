# Skill adaptations

These adaptations apply to skills that implement, review, and deliver a change. [SDLC.md](../../SDLC.md) defines the process and gates. [AGENTS.md](../../AGENTS.md#agent-skills) has the rules for every skill, and [issue-tracker.md](issue-tracker.md) covers the skills that create tasks.

## implement

- Implement only a task with `status: approved` on `main`, or a change the owner directly asks for without a task (SDLC §4). Given a draft or a task not yet on `main`, stop and say so.
- Work on a separate branch for that task, created and named as in [SDLC §4](../../SDLC.md#branches-and-prs).
- Use `tdd` for every AC on the [test-first path](../../SDLC.md#test-first-implementation), at the seam its test level belongs to in the [testing strategy](../testing-strategy.md#seams). This replaces the skill's "where possible" and the `tdd` step that confirms seams with the user; ask only when a test needs a new kind of seam.
- For the skill's checks use: typechecking `docker compose exec app composer analyse`; single tests `docker compose exec app php artisan test --compact <path-or-filter>`; single Behat scenarios `docker compose exec app vendor/bin/behat --strict --name=<scenario>`; the full suite at the end, the pull-request checks in [README](../../README.md#quality-checks), which must pass before pushing.
- Change the implemented task from `approved` to `done` on the task branch and leave every other task file unchanged. Raise any needed requirement change with the user; it goes through a separate task-change PR (SDLC §2).
- Make the red and production commits from SDLC §4 on the task branch, with the task ID in each message, in place of the skill's final commit.
- Skip the closing `code-review` self-review. Instead, follow the [SDLC §4 review flow](../../SDLC.md#review-flow): push the branch, open the PR as in [pr](#pr), and start one reviewer subagent with a fresh context. Its prompt contains only the PR number, the task ID or the direct-change note, the base SHA, and the head SHA. In Claude Code use the `independent-reviewer` subagent; any tool's reviewer follows [independent-review.md](independent-review.md).
- Invoking `implement` on an approved task authorizes the push, the PR, and the review. For a change without a task, push and open the PR only when the user asks. Never merge.
- Relay the reviewer's result and recommendation without editing them. Then check that the checked-out ref is unchanged and `git status --short` is empty; if not, the report is invalid, and tell the owner.
- After `FAIL`, change nothing until the owner asks. After every change to the head SHA that the owner asks for, including an update from `main`, start a new review without asking again.
- If the reviewer cannot run or returns no report, for example because of an exhausted quota or a tool error, stop and tell the owner in one line. Do not switch to another reviewer; a missing report still blocks merge.
- When the only changes after a `PASS` set tasks from `draft` to `approved`, apply the [status-only exception](../../SDLC.md#status-only-change-after-review).

## implement-spec

Not used in the MVP. It depends on subagents, parallel worktrees, and agent merges into an integration branch. Implement approved tasks one at a time with `implement`.

## code-review

`code-review` runs only as the independent review: in the reviewer subagent that the [SDLC §4 review flow](../../SDLC.md#review-flow) starts, or in a separate session the owner starts. The reviewer follows [independent-review.md](independent-review.md) and runs both axes one after the other, without further agents.

For the independent review:

- Don't modify code, commit, or push.
- The spec source is the approved task plus linked shared requirements, read from the base SHA (`git show <base-sha>:docs/tasks/<TASK-ID>.md`), or the PR description for a change without a task (SDLC §4). For a planning PR it is the SDLC §3 definition of ready and the requested documentation change. The branch's version of an approved task and `docs_internal/specs/` are never spec sources. Standards sources are AGENTS.md and the documents it makes mandatory.
- Diff against the PR's base branch. After `git fetch`, record the base SHA (`git merge-base origin/main HEAD`) and head SHA (`git rev-parse HEAD`). Confirm that the branch includes current `main` (`git merge-base --is-ancestor origin/main HEAD`); if it does not, stop and ask for the branch to be updated.
- For an implementation PR, compare task files with `git diff <base-sha> <head-sha> -- docs/tasks/` and apply the SDLC §4 rule: only `approved` to `done` for the implemented task is permitted. Report any change to governing documents or linked shared requirements as a finding.
- Run the Standards and Spec axes one after the other.
- Write the report in the [SDLC §4 format](../../SDLC.md#review-report): a visible block of at most 10 lines with one overall `PASS` or `FAIL`, the `BLOCKER` and `MAJOR` findings, the failed AC, and one recommended action, followed by a collapsed `<details>` section with the AC table, red-evidence results, `MINOR` findings, and the task-file comparison. This replaces the skill's rule against picking a single result.
- Report custom tooling that an existing tool could replace as a finding (AGENTS.md).
- For each AC on the test-first path, check its red evidence: check out the red commit, run the listed test with the narrowest command, confirm that it fails on an assertion, and return to the starting ref. Compare `git diff <red-sha> <head-sha> -- tests/` with the PR's deviations.
- An AC you cannot verify is `FAIL`; state whether the cause is a defect or missing evidence.
- The reviewer subagent posts the report with `gh pr comment`. In a session the owner started, hand the report to the owner for the PR. Fixes after a `FAIL` start only when the owner asks; there is no automatic fix loop.

## pr

- Use the [SDLC §4 PR description](../../SDLC.md#pull-request-description), in this order: the task ID with a link to the task file; `## Summary` with at most five bullets; a table of AC, test, and red commit SHA; `## Deviations`; and `## Risks`. This replaces the skill's Evidence and Merge Danger sections. For a change without a task, start with its requirements in place of the task ID.
- Show command output only where an AC requires the PR to show it, inside `<details>`.
- Title the PR `<type>: <TASK-ID> <description>`, or name the tasks as SDLC §4 describes for planning and task-change PRs.
- Open a PR only when the user asks, or when invoking `implement` on an approved task or `to-tickets` authorizes it (SDLC §4). Never merge.

## prototype

Skip step 6's commit and Issue comment. Report the prototype's path and verdict to the user; commit only on request.
