# Skill adaptations

These adaptations apply to skills that implement, review, and deliver a change. [SDLC.md](../../SDLC.md) defines the process and gates. [AGENTS.md](../../AGENTS.md#agent-skills) has the rules for every skill, and [issue-tracker.md](issue-tracker.md) covers the skills that create or publish tasks.

## implement

- Implement only a published Issue. If given a task file or an unpublished task, stop and say it has not been published.
- Work on a separate branch for that Issue, created and named as in [SDLC §4](../../SDLC.md#branches-and-prs).
- Use `tdd` for every AC on the [test-first path](../../SDLC.md#test-first-implementation), at the seam its test level belongs to in the [testing strategy](../testing-strategy.md#seams). This replaces the skill's "where possible" and the `tdd` step that confirms seams with the user; ask only when a test needs a new kind of seam.
- For the skill's checks use: typechecking `docker compose exec app composer analyse`; single tests `docker compose exec app php artisan test --compact <path-or-filter>`; single Behat scenarios `docker compose exec app vendor/bin/behat --strict --name=<scenario>`; the full suite at the end, the pull-request checks in [README](../../README.md#quality-checks).
- The closing `code-review` is the implementer's self-review. It does not replace the independent review.
- Make the red and production commits from SDLC §4 locally on the task branch, in place of the skill's final commit. Push only when the user asks.

## implement-spec

Not used in the MVP. It depends on subagents, parallel worktrees, and agent merges into an integration branch. Implement published Issues one at a time with `implement`.

## code-review

Unless the user starts an independent review, this is the implementer's self-review: run both axes in turn in this agent.

For the independent review, which a human starts in a separate session:

- Don't modify code, commit, or push.
- The spec source is the Issue plus applicable parent-Issue requirements, or the PR description for a change without an Issue (SDLC §4), never `docs/tasks/` or `docs_internal/specs/`. Standards sources are AGENTS.md and the documents it makes mandatory.
- Diff against the PR's base branch. After `git fetch`, record the base SHA (`git merge-base origin/main HEAD`) and head SHA (`git rev-parse HEAD`).
- Run the Standards and Spec axes one after the other and keep them in separate sections.
- Add what SDLC §4 requires: the Issue reference, both SHAs, a `BLOCKER`/`MAJOR`/`MINOR` classification with a reason for each finding, every applicable AC with `PASS`/`FAIL` and evidence, and one overall `PASS` or `FAIL`. This replaces the skill's rule against picking a single result.
- For each AC on the test-first path, check its red evidence: check out the red commit, run the listed test with the narrowest command, confirm that it fails on an assertion, and return to the head SHA. Compare `git diff <red-sha> <head-sha> -- tests/` with the PR's deviations.
- An AC you cannot verify is `FAIL`; state whether the cause is a defect or missing evidence.
- Hand the report to the user for the PR. After a `FAIL`, start fixes only when asked; there is no automatic fix loop.

## pr

- The body must contain the SDLC §4 items. Start with the Issue link, or with the requirements for a change without an Issue. Use Summary for the changes and Evidence for test commands and results, plus each AC's test and red commit SHA, add a `## Deviations` section, and use Merge Danger for risks.
- Open a PR only when the user asks. Never merge.

## prototype

Skip step 6's commit and Issue comment. Report the prototype's path and verdict to the user; commit or comment only on request.
