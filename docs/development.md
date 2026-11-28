# Development reference

This reference covers the local Docker Compose environment beyond what the [README](../README.md) shows: environment notes, PostgreSQL, RabbitMQ, failure recovery, and the CI quality checks. Run the commands from the project root.

## Environment notes

Sessions, cache and the queue use PostgreSQL and the standard Laravel migrations. The worker has a 90-second timeout, and `DB_QUEUE_RETRY_AFTER=120` prevents a job from being picked up again before that timeout expires. Mail goes to the application log (`MAIL_MAILER=log`).

`make down` also removes the `public/hot` marker, so once Vite stops, Laravel serves the previously built assets. If you run `docker compose down` directly and then work without Vite, remove the marker yourself. The standard `composer run dev` and `composer run setup` scripts come from the Laravel skeleton; in this environment use `make up` and `make setup`, which manage the separate containers.

## PostgreSQL

The application connects to host `postgres` on port `5432`. An IDE or another client on the host connects to `127.0.0.1:54320`. The default database and user are `trade_maneuver`, and the local password is `local_trade_maneuver_password` (configured in `.env`).

Tests use a separate database, `trade_maneuver_testing`, created when PostgreSQL is initialized and by `bin/setup`. `phpunit.xml` enforces its name and the `pgsql` driver. `AppServiceProvider` refuses `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback`, and `db:wipe` on any other database, so they cannot clear the development database. To start the development database again from an empty state, recreate its volume with `docker compose down -v`, which also clears RabbitMQ, and run `make setup`.

PostgreSQL data stays in the volume after `make down`. Changing the credentials in `.env` does not update the users of an existing cluster, because the PostgreSQL image only initializes an empty volume.

## RabbitMQ

`docker/rabbitmq/definitions.json` holds the topology, loaded at every start, in two vhosts: `trade-maneuver` for the application and `trade-maneuver-testing` for the tests. [The integration overview](integration.md#4-transport) describes the exchanges and queues.

`docker/rabbitmq/rabbitmq.conf` seeds the users with passwords from the environment, and `.env.example` has local defaults. Like the PostgreSQL users, RabbitMQ users are created only when the `rabbitmq_data` volume is empty. After adding or changing a user, recreate the volume with `docker compose down -v`, which also deletes the database, or add the user with `docker compose exec rabbitmq rabbitmqctl add_user` and `set_permissions -p trade-maneuver`.

Inspect the queues with `docker compose exec rabbitmq rabbitmqctl list_queues -p trade-maneuver name messages`.

## Failures and recovery

The intake transaction queues an evaluation job for each accepted trade signal, and each signal outcome gets a publication job. The `queue` service runs both from Laravel's PostgreSQL job queue.

- Trade signal consumer (`trade-signal-consumer`): it rejects a message that cannot be processed without requeue, so RabbitMQ moves it to the dead letter queue. When the database fails, it requeues the delivery and stops, and Compose restarts it. On SIGTERM it finishes the current delivery and stops; RabbitMQ requeues the deliveries it had prefetched.
- Evaluation: a job that a stopped or crashed worker had reserved runs again after `DB_QUEUE_RETRY_AFTER`, and a trade signal that already has a signal outcome is not evaluated again. A failed instrument lookup is a recoverable failure: the job runs again 30 seconds later and continues with the first step not yet passed.
- Publication of signal outcomes: the job publishes the outbox entry as a persistent message whose message id is the outcome message `id`, and marks it sent only after RabbitMQ confirms it. When RabbitMQ cannot route or does not confirm a signal outcome, the entry stays unsent, the failure is logged as an error, and the job is retried up to 10 times with a delay that doubles after each failed attempt, from 1 second to 256 seconds. When RabbitMQ drops the connection, for example after a restart, the attempt fails and the next one opens a new connection; if the queue worker stops, Compose restarts it. After the last attempt the job stays in `failed_jobs`, and `docker compose exec app php artisan queue:retry all` queues it again.

## CI quality checks

Run `make ci` (or `sh bin/ci`) from the host. It builds the PHP image with PCOV, installs locked Composer dependencies, starts PostgreSQL and RabbitMQ, ensures the separate test database exists, and runs `composer ci` in a disposable PHP container with test configuration. It keeps the existing application key and database data. PCOV is disabled during normal development and enabled for mutation coverage.

| Check | Configuration |
| --- | --- |
| Composer validation and `composer audit --locked` | `composer.json`, `composer.lock` |
| PHP CS Fixer in check mode: PSR-12 with explicit adjustments matching the existing Pint style | `.php-cs-fixer.dist.php` |
| PHPStan with Larastan at level 8, with cognitive complexity limits of 9 per function or method and 30 per class (`tomasvotruba/cognitive-complexity`), disallowed calls (`spaze/phpstan-disallowed-calls`), and architecture rules (`phpat/phpat`) | `phpstan.neon.dist`, `tests/Architecture/ArchitectureRules.php` |
| PHPMD with six rules at their default limits: `CyclomaticComplexity`, `NPathComplexity`, `ExcessiveMethodLength`, `ExcessiveParameterList` (constructors exempt), `EmptyCatchBlock`, and `UnusedLocalVariable` | `phpmd.xml` |
| Deptrac; run it separately with `docker compose exec app composer analyse:architecture` | `deptrac.yaml` |
| PHPUnit | `phpunit.xml` |
| Behat | `behat.php` |
| Infection | `infection.json5` |
| Browser tests with Playwright, `make e2e`, in the `e2e` job; see [Browser tests](#browser-tests) | `tests/Browser/playwright.config.js`, `bin/e2e`, the `e2e` profile in `compose.yaml` |
| Trivy image scan, `make scan-images`: known critical and high vulnerabilities in the OS packages of every image from `docker compose config --images`, when a fixed version exists | `bin/scan-images`, `.trivyignore.yaml` when an exception exists |

[The architecture](architecture.md#dependency-rules) states the rules that Deptrac, PHPat, and the PHPStan disallowed-calls entries enforce.

Infection uses minimum MSI 80% and covered MSI 100%. The full run checks all configured application sources (`app/` and `src/`), including uncovered code, and fails when no mutations are generated. The changed-line run checks only the lines changed since the merge base with `origin/main`. It ignores lines PHPUnit does not run and passes when the changes generate no mutations. [The testing strategy](testing-strategy.md#mutation-testing-with-infection) says what each run proves.

Local `make ci` includes the full Infection run by default. Use `CI_WITH_MUTATION=diff make ci` for the pull-request checks, after `git fetch` so that `origin/main` is current, and `CI_WITH_MUTATION=0 make ci` to skip Infection. `CI_SKIP_BUILD=1` reuses an already built PHP image, as the workflow does after its cached build step.

GitHub Actions runs `make ci` in the `quality` job; [SDLC §6](../SDLC.md#6-ci) states which Infection run each event gets. Docker build layers and Composer downloads are cached between workflow runs, and Composer downloads are kept in `storage/framework/cache/composer`. PHPUnit and Infection reports are written to `storage/quality/` and uploaded as workflow artifacts for seven days.

`make ci-lint` checks workflows, shell scripts, and Compose; GitHub Actions runs it before building the PHP image.

`make scan-images` runs the same image scan as CI. CI runs it in its own `image-scan` job, next to `quality` and outside `make ci`. The scan checks each image once. Trivy pulls the registry images itself and reads the locally built PHP image from a `docker save` archive, so its container gets no access to the Docker socket. The vulnerability database is kept in `storage/framework/cache/trivy`, and the workflow caches it for a day.

Every registry image in `compose.yaml`, and both images that `docker/php/Dockerfile` builds from, have an exact tag and a digest. A local scan and CI therefore check the same registry images, and both build the PHP image from the same PHP and Composer images. The PHP image can still differ in the packages its Dockerfile installs, because a build installs the versions available that day and the build cache keeps them. `docker compose build --no-cache app` rebuilds it with current packages.

To update a pinned image, a PR changes its tag and its digest, which `docker buildx imagetools inspect <name>:<tag>` shows, and the PR's scan checks the new image.

When a finding has to wait for a fix, add an exception to `.trivyignore.yaml` and create the file if it does not exist yet. The exception gives the vulnerability ID, an `expired_at` date, and a `statement` with the reason and the image it concerns. Narrow it with `purls` where the finding allows. `make scan-images` passes the file to Trivy when it exists. Each new exception needs the owner's approval in the PR that adds it.

```yaml
vulnerabilities:
  - id: <vulnerability-id>
    expired_at: <yyyy-mm-dd>
    statement: <reason>; <image>
    purls:
      - <package-url>
```

### Browser tests

`make e2e` (or `sh bin/e2e`) runs the Playwright tests in `tests/Browser/` in Chromium, against the application in the `e2e` Compose profile. The profile has its own services: `e2e-app` runs PHP-FPM from the same image and code as `app`, `e2e-nginx` serves it through the same nginx template with `PHP_FPM_HOST=e2e-app`, `e2e-node` builds the frontend, and `e2e-playwright` runs the tests in the official Playwright image. They use the existing PostgreSQL service with the database `trade_maneuver_e2e` and the RabbitMQ vhost `trade-maneuver-testing`.

`bin/e2e` builds the PHP image unless `CI_SKIP_BUILD=1`, installs the locked Composer dependencies, runs `npm ci` and `npm run build` in `e2e-node`, makes the build readable for the nginx workers, starts `e2e-app` and `e2e-nginx`, drops and recreates `trade_maneuver_e2e` with `psql`, migrates it, seeds the browser-test operator account `operator@example.test` with `BrowserTestOperatorSeeder` and the investment configuration and orders awaiting approval of `orders.html` with `BrowserTestOrderSeeder`, runs the tests, and removes the e2e services, also after a failure. Arguments go to Playwright, for example `sh bin/e2e --grep health`.

The development environment stays as it is:

- `e2e-node` keeps `node_modules` in the named volume `e2e_node_modules` and its npm cache in `storage/framework/cache/npm`, so the development Vite server keeps the working tree's `node_modules`. The build writes `public/build`, as `make build` does.
- `e2e-app` sets the test `APP_KEY`, its own database, `APP_CONFIG_CACHE` in `/tmp`, database sessions and cache, the `sync` queue, and `TRADING_ANALYTICS_APPLICATIONS` with `demo-analytics`, whose trade signals the order seeder creates, whatever `.env` says. `APP_VITE_HOT_FILE` points Laravel's Vite hot file at a path that does not exist, so it serves the production build even while the development Vite server runs and `public/hot` exists.
- `bin/e2e` names the e2e services in each `docker compose` call, so it neither recreates nor removes the development services.

Reports go to `storage/quality/playwright/`: the HTML report in `report/`, traces and failure screenshots in `test-results/`, and the expected images of the mockup comparisons in `snapshots/`. Each run clears the directory.

A failed mockup comparison names the page under test, the mockup page, the whole page or the compared element, and the theme. Open `storage/quality/playwright/report/index.html` in a browser, or the `playwright-report` artifact of a failed CI run: the failed test shows the mockup's screenshot as Expected, the page under test as Actual, and a Diff image with the differing pixels marked in red. The expected image comes from the mockup, so the helper fails a run with `--update-snapshots`, which would replace it.

GitHub Actions runs `make e2e` in the `e2e` job, on the same events as `quality` and in parallel with it. The job builds the PHP image with the same build cache, caches Composer downloads as `quality` does and npm downloads by `package-lock.json`, and uploads `storage/quality/playwright/` as the `playwright-report` artifact for seven days when it fails.

The Playwright image in `compose.yaml` and `@playwright/test` in `package.json` have the same exact version. Update them together in one PR: run `docker compose exec node npm install --save-dev --save-exact @playwright/test@<version>`, and set the image to the tag `v<version>-noble` with the digest that `docker buildx imagetools inspect mcr.microsoft.com/playwright:v<version>-noble` shows. A new version can change how pages render, and the PR's browser tests show the effect.

`make scan-images` does not scan the Playwright image. It runs only for tests and only reaches the local application. Under the `e2e` profile, it stays out of `docker compose config --images`.

