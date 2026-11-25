---
id: SDLC-009
status: done
---

# SDLC-009: Browser tests with Playwright

## Goal

Playwright tests can drive the GUI in a real browser, against the application running in its own Compose environment. They check what the PHP tests cannot: JavaScript behavior and the look of a page compared with its mockup page in `docs/gui-design/`. `make e2e` runs them locally, and a CI job runs them on every PR and every push to `main`. The development environment and its data stay untouched. This task delivers the tooling and its smoke tests; the product tasks write the tests of their screens.

## Scope

In scope:

- `@playwright/test` as a development dependency, and the official Playwright image pinned by tag and digest.
- A Compose profile `e2e` with its own PHP, nginx, Node.js, and Playwright services and its own database.
- `make e2e`, a script in `bin/` that it runs, and a CI job `e2e`.
- A helper that compares a page with a mockup page, and smoke tests of the environment and of that helper.
- [docs/testing-strategy.md](../testing-strategy.md), [docs/development.md](../development.md#ci-quality-checks), and [README](../../README.md#quality-checks).

Out of scope:

- Tests against a deployed system, which the testing strategy still plans for CD.
- Browsers other than Chromium.
- Browser tests of application screens, seeders, and test accounts. Each GUI task adds its own, starting with TM-010.
- Scanning the Playwright image with `make scan-images`.
- The `Protect main` Ruleset. After merge, the owner adds the new job as a required check (SDLC §5).

## Requirements

### Environment

- The Compose profile `e2e` adds a PHP service and an nginx service that serve the application from the same image and code as the development services, a Node.js service for the frontend build, and a Playwright service. They use the existing PostgreSQL service with a separate database, `trade_maneuver_e2e`, which `bin/e2e` creates the way `bin/ci` creates the test database. They use the RabbitMQ vhost of the tests, so the development vhost stays untouched.
- The e2e PHP service serves the production Vite build, even while the development Vite server runs and `public/hot` exists. A service provider points Laravel's Vite hot file at a path that does not exist when a config key, backed by an environment variable that only the e2e PHP service sets, names that path.
- The Node.js service of the profile uses the same image as the development `node` service and keeps `node_modules` in a named Docker volume, so `npm ci` does not touch the `node_modules` that the development Vite server uses. Its npm cache is `storage/framework/cache/npm`, which git ignores and the CI job caches.
- The Node.js and Playwright services run as `${LOCAL_UID}:${LOCAL_GID}`, like the other services, and `bin/e2e` detects the values as `bin/ci` does when they are unset. Before `npm ci`, `bin/e2e` gives that user the named `node_modules` volume, which Docker creates owned by root. The Playwright report and `test-results` go to `storage/quality/playwright/`, which git ignores.
- The e2e PHP service sets `APP_ENV=testing`, the fixed test `APP_KEY` of `bin/ci`, `DB_DATABASE=trade_maneuver_e2e`, an empty `DB_URL`, `QUEUE_CONNECTION=sync`, and the RabbitMQ vhost of the tests. It also sets `APP_CONFIG_CACHE` to a path in `/tmp`, as `bin/ci` does, so a development `bootstrap/cache/config.php` cannot point it at the development database. Unlike `bin/ci`, it sets `CACHE_STORE=database` and `SESSION_DRIVER=database`, so sessions and cached values last across requests and stay in the e2e database whatever the local `.env` says. `bin/e2e` copies `.env.example` to `.env` when `.env` is missing, as `bin/ci` does.
- `bin/e2e` installs the locked Composer dependencies as `bin/ci` does, builds the frontend in that service (`npm ci`, `npm run build`), starts the e2e services, drops and recreates `trade_maneuver_e2e` with `psql`, runs `php artisan migrate`, runs the Playwright tests, and stops and removes the e2e services. It names the e2e services in each `docker compose` call, because `--profile e2e up` or `down` without names would also recreate or remove the services that have no profile. Any failing step fails `make e2e`.
- The guard in `AppServiceProvider` that allows destructive database commands only on the test database stays as it is: `bin/e2e` resets the e2e database without `migrate:fresh`.
- The development services, their database, their containers, and the working tree's `node_modules` stay unchanged during and after `make e2e`.

### Tests

- The Playwright tests and their configuration live in `tests/Browser/`. They run in Chromium in the Playwright image, so a local run and CI render pages the same way.
- The mockup in `docs/gui-design/` is mounted read-only into the Playwright service and opened from disk. Application code and its build do not reference it.
- A comparison helper in `tests/Browser/` takes a mockup page and a page under test at a 1280×800 viewport, in the same theme, and passes when at most 1% of the pixels differ. It compares either the whole page or one element of each page, chosen by selector. It takes both screenshots with `animations: 'disabled'`, writes the mockup page's screenshot as the expected image, and checks the page under test with Playwright's `toMatchSnapshot` and `maxDiffPixelRatio: 0.01`. The expected images go to `storage/quality/playwright/` through Playwright's snapshot path setting, so none is committed. The helper fails when Playwright runs with `--update-snapshots`, because that run would replace the expected image with the screenshot of the page under test.
- A smoke test opens `/up` of the e2e application in Chromium and expects status 200.
- A smoke test compares a mockup page with itself through the helper, once as a whole page and once as one element, in both themes.
- On failure, the test output names the differing pages, and the CI job uploads the Playwright report with the screenshots and their difference images as an artifact.

### CI job

- A job `e2e` in the existing CI workflow runs on the same events as `quality`, in parallel with it. It builds the PHP image with the same build cache, caches Composer downloads as `quality` does and npm downloads keyed by `package-lock.json`, and runs `make e2e`. The job takes no secrets.

### Documentation

- [docs/testing-strategy.md](../testing-strategy.md): the pyramid gains a row for browser tests with Playwright against the local Compose environment. The seam list gains the GUI of the locally running application in a real browser. The planned E2E suite against a deployed system stays planned.
- [docs/development.md](../development.md#ci-quality-checks): how `make e2e` works, the `e2e` job, how to read a failed screenshot comparison, how to update the pinned Playwright image together with `@playwright/test`, and that `make scan-images` does not scan the Playwright image.
- [README](../../README.md#quality-checks): run `make e2e` before opening a pull request that changes the GUI.
- [docs/architecture.md](../architecture.md#laravel-integration): the test directories include `tests/Browser/`.

## Decisions

- Playwright (owner decision, 2026-11-24). It runs in an official image with the browsers and their system libraries, compares screenshots itself, and needs no PHP integration.
- The official image over browsers installed on the runner (owner decision, 2026-11-24). Screenshot comparisons depend on fonts and system libraries, and one image renders the same locally and in CI.
- A separate Compose profile with its own database (owner decision, 2026-11-24). The development database holds the owner's data, which the browser tests must not change.
- Mockup comparison with a 1% pixel tolerance, of the whole page or of one element (owner decision, 2026-11-24). A spike on 2026-11-24 with Playwright 1.65.1 compared `/login` with `sign-in.html` at 1280×800: both themes passed, and the only visible difference was the focus outline of the e-mail field.
- This task delivers the tooling and leaves the tests of application screens to the product tasks, as the [definition of ready](../../SDLC.md#definition-of-ready) requires (owner decision, 2026-11-25). TM-010 adds the first screen tests and is blocked by this task.
- The smoke tests open `/up` and compare a mockup page with itself (owner decision, 2026-11-25). `/up` is on `main` without any product task and checks the browser, the network between the services, nginx, and PHP. The self-comparison checks the mockup mount, the fonts, the tolerance, and the report, which product tasks rely on.
- A separate CI job, parallel to `quality` (owner decision, 2026-11-24). The repository is public, so GitHub Actions minutes cost nothing. The job is expected to take 2 to 3 minutes, shorter than `quality`.
- `bin/e2e` drops and recreates the e2e database with `psql` and then migrates it, instead of `migrate:fresh` (owner decision, 2026-11-24). The guard against destructive commands keeps allowing them only on the test database.
- The Playwright image is not scanned (owner decision, 2026-11-24). It runs only for tests, only reaches the local application, and never runs in production. It is a large Ubuntu image, so fixable findings in it would often block PRs or need lasting exceptions. Under the profile, it also stays out of `docker compose config --images`, which `make scan-images` scans.
- The frontend build for the browser tests runs in its own Node.js service with a named volume for `node_modules` (owner decision, 2026-11-24), so the development Vite server keeps its dependencies.
- `bin/e2e` is a script because no installed or Playwright tool covers its steps. Playwright's `webServer` option starts commands and waits for their URLs, but it runs inside the Playwright container, which has no access to Docker, so it cannot start the Compose services or reset the database. The script only calls `docker compose`, `npm`, `psql`, and `artisan`, as `bin/ci` does for CI.
- The comparison helper builds on Playwright's own assertion. This is proposed in this change; the owner confirms it when approving. `toHaveScreenshot` and `toMatchSnapshot` compare with a stored expected image and cannot take a second live page, so the helper only writes the mockup's screenshot to that image's path first. It uses `toMatchSnapshot`, because it takes the screenshot buffer that the helper already holds and its expected path is the plain snapshot path. In return, the helper disables animations itself, which `toHaveScreenshot` would do. The comparison, the difference images, and the report stay Playwright's, and no dependency is added. A spike on 2026-11-25 with Playwright 1.65.1 compared `sign-in.html` with itself as a whole page and as one element and passed, and with a temporary style changing its colors, it failed and the report held the expected, actual, and difference images. The same spike checked that screenshots with `animations: 'disabled'` still pass the self-comparison and that the helper can refuse a run with `--update-snapshots`, which Playwright reports as `updateSnapshots` set to `changed` or `all`.
- The hot-file setting is no tool of our own: it is one call to Laravel's `Vite::useHotFile()`, which has no config option.

## Constraints

- The change touches the workflow, `compose.yaml`, `bin/`, `Makefile`, and `package.json`, so follow [SDLC §6](../../SDLC.md#changes-to-ci-configuration).
- `@playwright/test` and the Playwright image use the same version. The image reference has the form `<name>:<tag>@sha256:<digest>`, with the multi-platform index digest (SDLC-006).
- Use the scratchpad or a temporary change for break checks, and do not commit them.

## Risks

- The Playwright image is about 1 GB, and CI pulls it on every run.
- Vulnerabilities in the Playwright image go unnoticed, because it is not scanned.
- A Playwright update can change rendering, so the image and the package are updated together in a PR whose browser tests show the effect.
- The 1% tolerance can hide a small change, such as one wrong color in a small element. Behavior checks and the PHP tests cover what the comparison misses.
- A mockup change makes the comparison fail until the application follows it. This is intended: the mockup sets the look.

## Acceptance criteria

- [x] Feature test: with the hot-file config key set, a page rendered while `public/hot` exists references the production build; without the key, it references the Vite dev server.
- [x] Browser test: `/up` of the e2e application answers with status 200.
- [x] Browser test: a mockup page matches itself through the comparison helper, as a whole page and as one element, in both themes.
- [x] Break check: with a temporary listener of Laravel's `DiagnosingHealth` event that throws, the `/up` test fails and `make e2e` exits with a non-zero status.
- [x] Browser test: the comparison helper fails when Playwright runs with `--update-snapshots`.
- [x] Break check: with a temporary style on the page under test that changes the page background color and the compared element's color, both self-comparisons fail in both themes and the report shows the difference images.
- [x] Manual check: with the development environment running and `public/hot` present, `make e2e` passes, and afterwards the development containers are still running, the development Vite server still serves the pages, the development database is unchanged, and the working tree's `node_modules` has the same modification time as before.
- [x] CI check: the PR's GitHub Actions run shows the `e2e` job running in parallel with `quality` and passing.
- [x] Documentation check: testing-strategy.md, development.md, README, and architecture.md state the items in Documentation.

## Dependencies

- Blocked by: none.
