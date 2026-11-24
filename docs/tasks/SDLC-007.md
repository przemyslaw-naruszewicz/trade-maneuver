---
id: SDLC-007
status: draft
---

# SDLC-007: Frontend build check in CI

## Goal

CI fails when the frontend does not build. `make ci` installs the locked npm dependencies and runs the production Vite build, so a broken stylesheet, script, font path, or Vite configuration stops a PR, locally and in GitHub Actions alike.

## Scope

In scope:

- A frontend build step in `bin/ci`, which `make ci` and the `quality` job run.
- Caching npm downloads between GitHub Actions runs, as Composer downloads are cached today.
- [docs/development.md](../development.md#ci-quality-checks), "CI quality checks".

Out of scope:

- An audit of the JavaScript dependencies (left out by SDLC-006 as well).
- JavaScript or CSS linters and unit tests.
- Browser or E2E tests, which the [testing strategy](../testing-strategy.md) plans separately.
- PHP tests that render pages. They keep using the committed test build in `tests/Fixtures/vite-build` and do not depend on this step (TM-010).
- The `Protect main` Ruleset. The step runs inside the existing `quality` job, so the required checks stay the same.

## Requirements

- `bin/ci` runs `npm ci` and then `npm run build` in the Compose `node` service, before the Composer checks. Either command failing fails `make ci`.
- npm downloads are kept in `storage/framework/cache/npm` for this run, and the workflow caches that directory with `actions/cache`, keyed by the hash of `package-lock.json`, as it does for Composer.
- The step runs for every `CI_WITH_MUTATION` value, including `0`.
- The output shows that the build step ran and which command failed.

### Documentation

In [docs/development.md](../development.md#ci-quality-checks), "CI quality checks":

- The table gains a row for the frontend build and its configuration (`package.json`, `package-lock.json`, `vite.config.js`).
- The text says that `make ci` replaces `node_modules` and `public/build` with the locked dependencies and a fresh production build.

## Decisions

- The build runs in `bin/ci`, not as a separate workflow step, so that `make ci` checks the same as CI (SDLC §6). This is proposed in this draft; the owner confirms it when approving.
- `npm ci` over `npm install`: it installs exactly `package-lock.json` and fails when the lock file does not match `package.json`.
- No new tool: the Compose `node` service and the `build` script in `package.json` already exist.
- Found in the TM-010 review (PR #40): until then no test rendered a page with `@vite`, and nothing in CI built the frontend.

## Constraints

- The change touches `bin/ci` and the workflow, so follow [SDLC §6](../../SDLC.md#changes-to-ci-configuration): run `make ci-lint`, run `CI_WITH_MUTATION=diff make ci`, then check the PR's GitHub Actions run.
- Use the scratchpad or a temporary change for break checks, and do not commit them.

## Risks

- `npm ci` deletes and reinstalls `node_modules`. A running `npm run dev` server may need a restart after a local `make ci`. If SDLC-008 runs local CI in a clean copy, the working tree is no longer affected.
- The step adds the time of `npm ci` and `vite build` to every run. A cache miss downloads all npm packages.
- The npm registry must be reachable. An outage fails the job.

## Acceptance criteria

- [ ] Break check: with a temporary import of a missing module in `resources/js/app.js`, `CI_WITH_MUTATION=0 make ci` fails at the build step and names the missing module.
- [ ] Break check: with a temporary version in `package.json` that `package-lock.json` does not satisfy, `CI_WITH_MUTATION=0 make ci` fails at `npm ci`.
- [ ] CI check: the PR's GitHub Actions run shows the build step passing inside `quality`, and a rerun restores the npm cache.
- [ ] Documentation check: development.md states the items in Documentation.

## Dependencies

- Blocked by: none.
