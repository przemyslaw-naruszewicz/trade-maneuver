---
id: SDLC-008
status: draft
---

# SDLC-008: Local CI checks only committed files

## Goal

A local `make ci` gives the same result as the `quality` job for the same commit. Files that git ignores or does not track, and uncommitted changes, cannot make the local run pass while GitHub Actions fails.

## Scope

In scope:

- Running the checks of `bin/ci` against the files of the current commit only.
- [docs/development.md](../development.md#ci-quality-checks), "CI quality checks", and [README](../../README.md#quality-checks), "Quality checks".

Out of scope:

- The checks themselves and their configuration.
- The GitHub Actions workflow, which already runs on a fresh checkout.
- `make scan-images`, which scans images, not the working tree.

## Requirements

- A local `make ci` checks the committed state of `HEAD`. Ignored files (for example `public/build`, `public/hot`, `.env`) and untracked files from the working tree do not reach the checks.
- `make ci` keeps working while the development environment runs: it does not stop, recreate, or change the state of the running containers, and it does not change the working tree, except the reports in `storage/quality/` and the caches in `storage/framework/cache/`.
- With uncommitted changes, `make ci` says that it checks only `HEAD`.
- The Composer and npm caches stay shared with the working tree, so a run does not download all packages again.

## Decisions

- Found in the TM-010 review (PR #40): local `make ci` passed and the `quality` job failed. The local run saw a `public/hot` file from the running Vite dev server and a local `public/build`, both ignored by git, and the GitHub checkout had neither.
- Open before approval: how `bin/ci` gets a clean copy. Options for a spike outside the PR:
  - `git worktree add` of `HEAD` in a temporary directory, with the Compose project directory set to it, while PostgreSQL and RabbitMQ stay those of the running environment;
  - `git archive HEAD` unpacked in a temporary directory and mounted into the disposable PHP container in place of the project mount;
  - keeping the project mount and refusing to run when the working tree is dirty. This does not cover ignored files, so it does not meet the goal on its own.

  The spike measures the time of each option and checks the requirement on the running environment. The owner chooses an option before approval, and this task then names it.
- No new tool: git and Docker Compose cover every option.

## Constraints

- The change touches `bin/ci` and possibly `compose.yaml`, so follow [SDLC §6](../../SDLC.md#changes-to-ci-configuration).
- The local and the GitHub Actions run use the same script, as today.

## Risks

- A clean copy needs its own `vendor` and `node_modules`. Shared caches shorten the install, but a run gets longer.
- A developer who forgets to commit gets a check of the previous commit. The message about uncommitted changes reduces that risk.

## Acceptance criteria

- [ ] Break check: on a temporary commit with a test that renders a page against the default public path, and with an ignored `public/hot` file and no `public/build` in the working tree, `CI_WITH_MUTATION=0 make ci` fails as the `quality` job does. Today it passes.
- [ ] Break check: with a temporary untracked test file that fails, `CI_WITH_MUTATION=0 make ci` passes and says that it checks only `HEAD`.
- [ ] Manual check: with the development environment running, `make ci` leaves its containers running and unchanged, and `git status` shows no change after the run.
- [ ] Documentation check: development.md and README say that local CI checks the committed state of `HEAD`.

## Dependencies

- Blocked by: none.
