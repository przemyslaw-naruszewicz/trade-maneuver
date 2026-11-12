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
| PHPStan with Larastan at level 8, with cognitive complexity limits of 9 per function or method and 40 per class (`tomasvotruba/cognitive-complexity`) and disallowed calls (`spaze/phpstan-disallowed-calls`) | `phpstan.neon.dist` |
| Deptrac; run it separately with `docker compose exec app composer analyse:architecture` | `deptrac.yaml` |
| PHPUnit | `phpunit.xml` |
| Behat | `behat.php` |
| Infection | `infection.json5` |

[The architecture](architecture.md#laravel-integration) states the rules that Deptrac and the PHPStan disallowed-calls entries enforce.

Infection uses minimum MSI 80% and covered MSI 100%. The full run checks all configured application sources (`app/` and `src/`), including uncovered code, and fails when no mutations are generated. The changed-line run checks only the lines changed since the merge base with `origin/main`. It ignores lines PHPUnit does not run and passes when the changes generate no mutations. [The testing strategy](testing-strategy.md#mutation-testing-with-infection) says what each run proves.

Local `make ci` includes the full Infection run by default. Use `CI_WITH_MUTATION=diff make ci` for the pull-request checks, after `git fetch` so that `origin/main` is current, and `CI_WITH_MUTATION=0 make ci` to skip Infection. `CI_SKIP_BUILD=1` reuses an already built PHP image, as the workflow does after its cached build step.

GitHub Actions runs `make ci`; [SDLC §6](../SDLC.md#6-ci) states which Infection run each event gets. Docker build layers and Composer downloads are cached between workflow runs, and Composer downloads are kept in `storage/framework/cache/composer`. PHPUnit and Infection reports are written to `storage/quality/` and uploaded as workflow artifacts for seven days.

`make ci-lint` checks workflows, shell scripts, and Compose; GitHub Actions runs it before building the PHP image. For CI configuration changes, follow the [three-step verification process](../SDLC.md#changes-to-ci-configuration).
