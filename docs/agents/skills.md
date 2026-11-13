# Skill adaptations

These adaptations state how the steps of the skills that implement, review, and deliver a change differ from the skills. [SDLC.md](../../SDLC.md) defines the process and gates. [AGENTS.md](../../AGENTS.md#agent-skills) has the rules for every skill, and [issue-tracker.md](issue-tracker.md) covers the skills that create tasks.

## implement

- Implement only a task with `status: approved` on `main`, or a change the owner directly asks for without a task ([SDLC §4](../../SDLC.md#branches-and-prs)). Given a draft or a task not yet on `main`, stop and say so.
- Work on a separate branch for that task, created and named as in [SDLC §4](../../SDLC.md#branches-and-prs).
- Use `tdd` for every AC on the [test-first path](../../SDLC.md#test-first-implementation), at the seam its test level belongs to in the [testing strategy](../testing-strategy.md#seams). This replaces the skill's "where possible" and the `tdd` step that confirms seams with the user; ask only when a test needs a new kind of seam.
- For the skill's checks use: typechecking `docker compose exec app composer analyse`; single tests `docker compose exec app php artisan test --compact <path-or-filter>`; single Behat scenarios `docker compose exec -T -e APP_ENV=testing -e DB_DATABASE=trade_maneuver_testing -e QUEUE_CONNECTION=sync -e RABBITMQ_VHOST=trade-maneuver-testing app vendor/bin/behat --strict --name=<scenario>`, with the test environment that `bin/ci` sets, because the Trading suite refuses the development database; the full suite at the end, the pull-request checks in [README](../../README.md#quality-checks), which must pass before pushing.
- Make the red and production commits from [SDLC §4](../../SDLC.md#test-first-implementation) on the task branch, with the task ID in each message, in place of the skill's final commit.
- In the commit that sets the implemented task to `done`, tick (`- [x]`) each AC that the change meets, before the review starts. Change no other task file ([SDLC §4](../../SDLC.md#branches-and-prs)), and raise any needed requirement change with the user (SDLC §2).
- Handle the owner's `Task adaptation approved:` or `Task adaptation declined:` comment as [SDLC §4](../../SDLC.md#task-adaptation-found-in-review) describes.
- Skip the skill's closing call to Matt Pocock's `code-review` as a self-review. Instead, push the branch, open the PR as in [pr](#pr), and start the reviewer as the [SDLC §4 review flow](../../SDLC.md#review-flow) describes. In Claude Code use the `independent-reviewer` subagent.
- After the reviewer returns, relay its result and check the working tree as the review flow describes.
- Never merge (SDLC §5).
- After `FAIL`, change nothing until the owner asks (SDLC §4).
- If the reviewer cannot run or returns no report, stop, tell the owner in one line, and start no other reviewer (SDLC §4).
- When the only changes after a `PASS` set tasks from `draft` to `approved`, apply the [status-only exception](../../SDLC.md#status-only-change-after-review).

## implement-spec

Not used in the MVP. It depends on subagents, parallel worktrees, and agent merges into an integration branch. Implement approved tasks one at a time with `implement`.

## code-review

`code-review` here means the skill from Matt Pocock's [mattpocock/skills](https://github.com/mattpocock/skills), `mattpocock-skills:code-review` in Claude Code. The project uses plugin `mattpocock-skills` 1.3.1, whose `code-review` is [this upstream version](https://github.com/mattpocock/skills/blob/49dd158d1076134a641b33efb035946536778336/skills/engineering/code-review/SKILL.md). An agent tool that does not have the skill follows that version's steps with the adaptations below. A PR that updates the plugin also updates the version and the link.

`code-review` runs only as the independent review, following [independent-review.md](independent-review.md).

In an [incremental review](../../SDLC.md#incremental-review):

- The skill's fixed point in step 1 is the reviewed SHA, so the diff is `git diff <reviewed-sha> <head-sha>`.
- After an update from `main`, `git range-diff <old-base>..<reviewed-sha> <base-sha>..<head-sha>` replaces the skill's three-dot diff, so the PR's own changes stay apart from what came from `main`.

## pr

- Write the [SDLC §4 PR description](../../SDLC.md#pull-request-description), within its limits: about 250 visible words, tables inside `<details>`, no restated task content, and "None" for empty Deviations and Risks. It replaces the skill's template: the Summary's diagram, the Evidence section, and the Merge Danger section.
- Title the PR as [SDLC §4](../../SDLC.md#branches-and-prs) describes.
- Never merge (SDLC §5).

## prototype

Skip step 6's commit and Issue comment. Report the prototype's path and verdict to the user; commit only on request.
