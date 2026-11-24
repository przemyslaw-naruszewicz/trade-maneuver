---
id: SDLC-009
status: draft
---

# SDLC-009: Browser tests with Playwright

## Goal

Playwright tests drive the GUI in a real browser, against the application running in its own Compose environment. They check what the PHP tests cannot: JavaScript behavior, such as the theme switch, and the look of a page compared with its mockup page in `docs/gui-design/`. `make e2e` runs them locally, and a CI job runs them on every PR and every push to `main`. The development environment and its data stay untouched.

## Scope

In scope:

- `@playwright/test` and `otpauth` as development dependencies, and the official Playwright image pinned by tag and digest.
- A Compose profile `e2e` with its own PHP, nginx, Node.js, and Playwright services and its own database.
- A seeder for the browser-test operator account.
- `make e2e`, a script in `bin/` that it runs, and a CI job `e2e`.
- The first browser tests, for the TM-010 screens.
- [docs/testing-strategy.md](../testing-strategy.md), [docs/development.md](../development.md#ci-quality-checks), and [README](../../README.md#quality-checks).

Out of scope:

- Tests against a deployed system, which the testing strategy still plans for CD.
- Browsers other than Chromium.
- Browser tests for screens of later tasks. Each GUI task adds its own.
- Scanning the Playwright image with `make scan-images`.
- The `Protect main` Ruleset. After merge, the owner adds the new job as a required check (SDLC §5).

## Requirements

### Environment

- The Compose profile `e2e` adds a PHP service and an nginx service that serve the application from the same image and code as the development services, a Node.js service for the frontend build, and a Playwright service. They use the existing PostgreSQL service with a separate database, `trade_maneuver_e2e`, which `bin/e2e` creates the way `bin/ci` creates the test database. They use the RabbitMQ vhost of the tests, so the development vhost stays untouched.
- The e2e PHP service serves the production Vite build, even while the development Vite server runs and `public/hot` exists. A service provider points Laravel's Vite hot file at a path that does not exist when a config key, backed by an environment variable that only the e2e PHP service sets, names that path.
- The Node.js service of the profile uses the same image as the development `node` service and keeps `node_modules` in a named Docker volume, so `npm ci` does not touch the `node_modules` that the development Vite server uses.
- The e2e PHP service gets its environment as `bin/ci` sets it for the tests: a fixed test `APP_KEY`, `DB_DATABASE=trade_maneuver_e2e`, an empty `DB_URL`, the RabbitMQ vhost of the tests, and the synchronous queue. Sessions keep the application's session driver, so a session lasts across requests.
- `bin/e2e` installs the locked Composer dependencies as `bin/ci` does, builds the frontend in that service (`npm ci`, `npm run build`), starts the profile, drops and recreates `trade_maneuver_e2e` with `psql`, runs `php artisan migrate`, seeds the browser-test operator, runs the Playwright tests, and stops the profile's services. Any failing step fails `make e2e`.
- The guard in `AppServiceProvider` that allows destructive database commands only on the test database stays as it is: `bin/e2e` resets the e2e database without `migrate:fresh`.
- The development services, their database, their containers, and the working tree's `node_modules` stay unchanged during and after `make e2e`.

### Tests

- The Playwright tests and their configuration live in `tests/Browser/`. They run in Chromium in the Playwright image, so a local run and CI render pages the same way.
- The mockup in `docs/gui-design/` is mounted read-only into the Playwright service and opened from disk. Application code and its build still do not reference it (TM-010).
- The seeder creates the operator account with a fixed fictional e-mail address and password through the create-operator use case that TM-010 adds, then gives it a fixed TOTP secret through the `Operator` aggregate's TOTP enrolment, saved through the operator repository in a transaction. The tests compute TOTP codes with `otpauth` from that secret.
- The tests log in once per run, in a Playwright setup project that saves the session (`storageState`). The panel tests reuse that session. The test that logs out runs in a last project, after every other panel test, because logging out ends the session. A further login in the same run uses the code of the next time step, which TM-010's tolerance of one step accepts.
- A screenshot comparison takes the mockup page and the application page at a 1280×800 viewport and the same theme, and passes when at most 1% of the pixels differ. A whole-page comparison is used where the application page and the mockup page show the same content. `totp-challenge.html` shows the error state, so the test first submits a wrong code, then types the mockup's value `480216` into the field, and compares the page in that state. Elsewhere the tests compare the shared elements one by one, because the mockup shows elements of later tasks: on the start page, the brand, the Start navigation entry, the theme switch, the logout action, and the page title.
- On failure, the test output names the differing pages, and the CI job uploads the Playwright report with the screenshots and their difference images as an artifact.

### CI job

- A job `e2e` in the existing CI workflow runs on the same events as `quality`, in parallel with it. It builds the PHP image with the same build cache, caches Composer downloads as `quality` does and npm downloads keyed by `package-lock.json`, and runs `make e2e`. The job takes no secrets.

### Documentation

- [docs/testing-strategy.md](../testing-strategy.md): the pyramid gains a row for browser tests with Playwright against the local Compose environment. The seam list gains the GUI of the locally running application in a real browser. The planned E2E suite against a deployed system stays planned.
- [docs/development.md](../development.md#ci-quality-checks): how `make e2e` works, the `e2e` job, how to read a failed screenshot comparison, how to update the pinned Playwright image together with `@playwright/test`, and that `make scan-images` does not scan the Playwright image.
- [README](../../README.md#quality-checks): run `make e2e` before opening a pull request that changes the GUI.

## Decisions

- Playwright (owner decision, 2026-11-24). It runs in an official image with the browsers and their system libraries, compares screenshots itself, and needs no PHP integration.
- The official image over browsers installed on the runner (owner decision, 2026-11-24). Screenshot comparisons depend on fonts and system libraries, and one image renders the same locally and in CI.
- A separate Compose profile with its own database (owner decision, 2026-11-24). The development database holds the owner's operator account, and the application allows only one.
- `otpauth` over a TOTP implementation of our own (owner decision, 2026-11-24): MIT, version 9.6.0 when drafted.
- Mockup comparison with a 1% pixel tolerance; for screens whose content differs from the mockup, only the shared elements (owner decision, 2026-11-24). `start.html` shows the Orders and Investment configuration entries and the execution mode of later tasks, so a whole-page or whole-panel comparison of the start page would fail. A spike on 2026-11-24 with Playwright 1.65.1 compared `/login` with `sign-in.html` at 1280×800: both themes passed, and the only visible difference was the focus outline of the e-mail field.
- A separate CI job, parallel to `quality` (owner decision, 2026-11-24). The repository is public, so GitHub Actions minutes cost nothing. The job is expected to take 2 to 3 minutes, shorter than `quality`.
- `bin/e2e` drops and recreates the e2e database with `psql` and then migrates it, instead of `migrate:fresh` (owner decision, 2026-11-24). The guard against destructive commands keeps allowing them only on the test database.
- The Playwright image is not scanned (owner decision, 2026-11-24). It runs only for tests, only reaches the local application, and never runs in production. It is a large Ubuntu image, so fixable findings in it would often block PRs or need lasting exceptions. Under the profile, it also stays out of `docker compose config --images`, which `make scan-images` scans.
- The frontend build for the browser tests runs in its own Node.js service with a named volume for `node_modules` (owner decision, 2026-11-24), so the development Vite server keeps its dependencies.
- These tests check the look of the TM-010 screens in both themes and the theme behavior from then on, so later changes cannot break them unnoticed (owner decision, 2026-11-24). Whether TM-010 keeps its manual-check AC is a separate change of that task.
- `bin/e2e` is a script because no installed or Playwright tool covers its steps. Playwright's `webServer` option starts one command and waits for a URL, but it cannot start several Compose services, reset a database, or run a seeder, and the Playwright container has no access to Docker. The script only calls `docker compose`, `npm`, `psql`, and `artisan`, as `bin/ci` does for CI.
- The hot-file setting is no tool of our own: it is one call to Laravel's `Vite::useHotFile()`, which has no config option.
- The TOTP page is compared in the error state that its mockup shows (owner decision, 2026-11-24). The application's error message is the mockup's, so the comparison also checks the look of the error state. The wrong code counts as one failed attempt, and the correct login that follows clears the count.
- One login per run, shared through `storageState`, with the logout test last (owner decision, 2026-11-24). TM-010 accepts a TOTP code only once per 30-second step, and the seeded account is the only one, so a login in every test would be refused or would wait up to 30 seconds. Each run recreates the e2e database, so runs do not block each other.
- The spike checked that a stored dark theme is set when the browser parses `<body>`, before the first paint, through an init script with a `MutationObserver`.

## Constraints

- The change touches the workflow, `compose.yaml`, `bin/`, `Makefile`, and `package.json`, so follow [SDLC §6](../../SDLC.md#changes-to-ci-configuration).
- `@playwright/test` and the Playwright image use the same version. The image reference has the form `<name>:<tag>@sha256:<digest>`, with the multi-platform index digest (SDLC-006).
- Use the scratchpad or a temporary change for break checks, and do not commit them.

## Risks

- The Playwright image is about 1 GB, and CI pulls it on every run.
- Vulnerabilities in the Playwright image go unnoticed, because it is not scanned.
- A panel test that ends the shared session early, or a test order that Playwright changes, makes the later panel tests fail. The project order keeps the logout test last.
- A Playwright update can change rendering, so the image and the package are updated together in a PR whose browser tests show the effect.
- The 1% tolerance can hide a small change, such as one wrong color in a small element. Behavior checks and the PHP tests cover what the comparison misses.
- A mockup change makes the comparison fail until the application follows it. This is intended: the mockup sets the look.

## Acceptance criteria

- [ ] Browser test: the login page renders the light theme by default, and with the dark theme stored, the theme is dark when `<body>` is parsed.
- [ ] Browser test: the seeded operator logs in with the password and a TOTP code and reaches the start page; the panel's theme switch changes the theme, the choice survives a reload, and after logout the login page applies it.
- [ ] Browser test: the login page and the TOTP page match `sign-in.html` and `totp-challenge.html`, and the start page's brand, Start navigation entry, theme switch, logout action, and page title match those of `start.html`, in both themes, with at most 1% of the pixels differing.
- [ ] Break check: with the stored theme applied only after the page has loaded, the first-paint test fails.
- [ ] Break check: with the theme switch temporarily not storing the choice, the test of the choice surviving a reload fails.
- [ ] Break check: with a temporary change of one color token in `resources/css/app.css`, a mockup comparison fails and the report shows the difference image.
- [ ] Manual check: with the development environment running and `public/hot` present, `make e2e` passes, and afterwards the development containers are still running, the development Vite server still serves the pages, the development database is unchanged, and the working tree's `node_modules` has the same modification time as before.
- [ ] CI check: the PR's GitHub Actions run shows the `e2e` job running in parallel with `quality` and passing.
- [ ] Documentation check: testing-strategy.md, development.md, and README state the items in Documentation.

## Dependencies

- Blocked by: TM-010.
