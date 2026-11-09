# Trade Maneuver

> [!IMPORTANT]
> Trade Maneuver is a simulation, built as a portfolio and educational project. Its broker, brokerage accounts, instruments, prices, and amounts are fictional, and so are the trade signals of its local flow. It has no connection to a real market, real funds, or a real brokerage account.

Trade Maneuver will be an execution microservice: it receives trade signals from analytics applications, evaluates them against its domain rules, places orders with a simulated, fictional broker, and reports their outcomes. Integration with a real broker is out of scope. See [the integration overview](docs/integration.md) for an outline of the planned solution.

Follow the [SDLC contract](SDLC.md) for task preparation, approval, review, and delivery. It distinguishes the current CI pipeline from planned automation and CD.

The stack is Laravel, PHP, and PostgreSQL. Versions are defined in `composer.json`, `compose.yaml`, and `docker/php/Dockerfile`. The local environment runs in Docker Compose; the host only needs Docker with Compose v2+ and a `sh` shell. Make is optional.

## What is simulated

The repository currently holds an application skeleton, so this table describes the planned flow.

| Element | What it is here |
| --- | --- |
| Trade signals | The local flow gets them from the demo analytics application, a fictional test producer that uses invented values. It has no analytical model and recommends nothing. |
| Instruments | Fictional symbols starting with `DEMO_` on the fictional exchange `DEMO`. A trade signal naming an instrument unknown to the simulated broker is declined. |
| Orders | Simulated orders that reach only the simulated broker. |
| Broker | The simulated broker is the only broker. It imitates order acceptance, fills, rejections, and failures. |
| Brokerage accounts | Fictional accounts at the simulated broker. They hold no real funds and do not correspond to accounts at a real broker. |
| Prices and amounts | Invented. They represent no real money, even where they use a real currency code such as EUR. |

## Disclaimer

This is a portfolio and educational project, published only so that others can read and review the code. It is designed to run only against a simulated, fictional broker and places no real orders. It offers no hosted access, signal feed, or account management to anyone, and it is not intended for use by anyone other than its owner. Do not run it against a live brokerage account.

Nothing in this repository or in the software's output, including trade signals, orders, investment configurations, documentation, and examples, is investment advice, an investment recommendation, or an offer or encouragement to buy, sell, or hold any asset. The trade signals, instruments, prices, and amounts used in this project and its examples are fictional and illustrative only. This project is not a regulated investment service.

Anyone who runs or changes this code despite that does so at their own risk and is solely responsible for their configuration, the resulting orders and outcomes, and compliance with applicable law, tax obligations, and broker terms. Investing involves risk, including the loss of the entire amount invested. Automated execution adds risks such as software defects, misconfiguration, inaccurate data, and broker or network failures.

The software is provided "as is", without warranty of any kind. To the maximum extent permitted by law, its authors are not liable for any loss arising from its use, including losses from orders it places or fails to place.

## First run

```sh
./bin/setup
# or: make setup
```

The script creates `.env`, builds the PHP image, matches UID/GID to the owner of the project directory (including rootless Docker), installs dependencies, generates a missing `APP_KEY`, runs migrations and builds the frontend. It then starts all services and waits until they are healthy. It is safe to run again; it keeps the existing key and the data in the database.

The application runs at http://localhost:8080, and the Laravel health check at http://localhost:8080/up.

If you need different ports, copy `.env.example` to `.env` before starting and change `APP_PORT`, `APP_URL`, `VITE_PORT` or `FORWARD_DB_PORT`. `DB_PORT=5432` is the port inside the Compose network and stays unchanged. `VITE_HOST=localhost` is the host name the browser uses to reach Vite.

## Services

| Container | Purpose | Host access |
| --- | --- | --- |
| `nginx` | HTTP, static files, forwarding requests to PHP-FPM | `127.0.0.1:8080` |
| `app` | PHP-FPM, Artisan, Composer | via Nginx |
| `postgres` | PostgreSQL, persistent `postgres_data` volume | `127.0.0.1:54320` |
| `rabbitmq` | RabbitMQ for trade signals and signal outcomes, persistent `rabbitmq_data` volume | internal |
| `trade-signal-consumer` | `trading:consume-trade-signals`, which consumes trade signals from RabbitMQ | internal |
| `queue` | Separate `queue:work` worker, which also publishes signal outcomes from the outbox to RabbitMQ | internal |
| `scheduler` | Separate `schedule:work` process | internal |
| `node` | Node.js, Vite with hot reload | `127.0.0.1:5173` |

The project directory is mounted into the containers, so editing PHP does not require rebuilding the image. After changing job code run `docker compose restart queue`; after changing the schedule run `docker compose restart scheduler`; after changing the consumer run `docker compose restart trade-signal-consumer`.

Sessions, cache and the queue use PostgreSQL and the standard Laravel migrations. The worker has a 90-second timeout, and `DB_QUEUE_RETRY_AFTER=120` prevents a job from being picked up again before that timeout expires. Mail goes to the application log (`MAIL_MAILER=log`).

## Everyday work

```sh
make up                       # start services after the first setup
make down                     # stop; keep the database volume
make logs                     # service logs
make shell                    # shell in the PHP container
make migrate                  # run new migrations
make test                     # tests on a separate PostgreSQL database
make build                    # build frontend assets
make format                   # Laravel Pint
make ci                       # complete local CI pipeline in Docker
make ci-lint                  # validate workflows, shell scripts and Compose
```

Without Make:

```sh
docker compose up -d --wait
docker compose exec app php artisan migrate
docker compose exec app php artisan make:model Trade -mf
docker compose exec app php artisan tinker
docker compose exec app composer require vendor/package
docker compose exec node npm install
docker compose exec app php artisan test --compact
docker compose logs -f
```

`make down` also removes the `public/hot` marker, so once Vite stops, Laravel serves the previously built assets. If you run `docker compose down` directly and then work without Vite, remove the marker yourself. The standard `composer run dev` and `composer run setup` scripts come from the Laravel skeleton; in this environment use `make up` and `make setup`, which manage the separate containers.

## Investment configuration

Trade Maneuver has exactly one investment configuration, created once at deployment. Register the known analytics applications in `TRADING_ANALYTICS_APPLICATIONS` as comma-separated `<source>:<RabbitMQ user>` entries: the identifier each one sends as `source` and the RabbitMQ user it publishes as, for example `demo-analytics:demo-analytics`. The identifier is also the routing key of the analytics application's outcome queue. Then run:

```sh
docker compose exec app php artisan trading:create-investment-configuration demo-investment \
    --currency=EUR --brokerage-account=DEMO-ACCOUNT-1 --order-amount=200.00 \
    --limit-price-tolerance=1.50 --authorized-analytics-application=demo-analytics
```

The reference (`demo-investment`) is what trade signals send as `investmentConfigurationRef`. The currency is EUR. The order amount is in that currency unless `--order-amount-currency` says otherwise, and the tolerance is a percentage from 0 to 5. Repeat `--authorized-analytics-application` for each application, or leave it out to reject every trade signal. The command refuses invalid input and a second investment configuration without writing anything, and records the creation in the audit trail.

## RabbitMQ

RabbitMQ carries trade signals from analytics applications to Trade Maneuver and signal outcomes back to them ([ADR-0003](docs/ADR/adr-0003-rabbitmq-for-integration-messaging.md)). It publishes no host port. The container has a 512 MB memory limit, and RabbitMQ blocks publishers at an absolute threshold of 256 MiB. `docker/rabbitmq/definitions.json` holds the topology, loaded at every start, in two vhosts: `trade-maneuver` for the application and `trade-maneuver-testing` for the tests.

| Element | Purpose |
| --- | --- |
| `tm.trade-signals` | Fanout exchange to which analytics applications publish trade signals |
| `tm.trade-signals.intake` | Trade Maneuver's durable intake queue, dead-lettering to `tm.trade-signals.dead-letter` |
| `tm.trade-signals.dead-letter` | Exchange and queue for messages that cannot be processed, kept for manual inspection |
| `tm.signal-outcomes` | Direct exchange for signal outcomes, routed by the analytics application identifier |
| `tm.signal-outcomes.<identifier>` | Durable outcome queue of one analytics application |

`docker/rabbitmq/rabbitmq.conf` seeds the users with passwords from the environment: `trade-maneuver` (`RABBITMQ_PASSWORD`) and the producer users of the fictional analytics applications, `demo-analytics` (`DEMO_ANALYTICS_RABBITMQ_PASSWORD`) and `demo-research` (`DEMO_RESEARCH_RABBITMQ_PASSWORD`). `.env.example` has local defaults. A producer user may write only to `tm.trade-signals` and read only its own outcome queue, and none has a tag, so none may set another user's `user_id`. Like the PostgreSQL users, RabbitMQ users are created only when the `rabbitmq_data` volume is empty: after adding or changing a user, recreate the volume with `docker compose down -v`, which also deletes the database, or add the user with `docker compose exec rabbitmq rabbitmqctl add_user` and `set_permissions -p trade-maneuver`.

Connecting another analytics application takes only configuration; [the integration overview](docs/integration.md#connecting-an-analytics-application) lists the steps.

The trade signal consumer acknowledges a delivery only after the intake transaction commits. It rejects a message that cannot be processed without requeue, so RabbitMQ moves it to the dead letter queue. When the database fails, it requeues the delivery and stops, and Compose restarts it. On SIGTERM it finishes the current delivery and stops; RabbitMQ requeues the deliveries it had prefetched. The intake use case queues a publication job for each signal outcome in its own transaction, on Laravel's PostgreSQL job queue, and the `queue` service runs it. The job publishes the outbox entry as a persistent message whose message id is the outcome message `id`, and marks it sent only after RabbitMQ confirms it. When RabbitMQ cannot route or does not confirm a signal outcome, the entry stays unsent, the failure is logged as an error, and the job is retried up to 10 times with a delay that doubles after each failed attempt, from 1 second to 256 seconds. When RabbitMQ drops the connection, for example after a restart, the attempt fails and the next one opens a new connection; if the queue worker stops, Compose restarts it. After the last attempt the job stays in `failed_jobs`; `docker compose exec app php artisan queue:retry all` queues it again.

To publish a trade signal and read its signal outcome, use the [demo analytics application](#demo-analytics-application).

Inspect the queues with `docker compose exec rabbitmq rabbitmqctl list_queues -p trade-maneuver name messages`.

## Demo analytics application

The demo analytics application is a simulated stand-in for an analytics application, so the flow can run locally without a real one. Its trade signals name fictional instruments on the fictional exchange `DEMO` and carry invented values. It has no analytical model, reads no market data, and recommends nothing. Its code lives in `src/DemoAnalytics`, outside the Trading context. It connects the way any analytics application does: through RabbitMQ, as the producer user `demo-analytics`, with `tm.trade-signal.v1` messages. Its registry entry and producer user exist only in the local and CI configuration.

1. Create an investment configuration that authorizes it with the command under [Investment configuration](#investment-configuration). `DEMO_ANALYTICS_INVESTMENT_CONFIGURATION_REF` names the investment configuration its trade signals address; `.env.example` sets it to `demo-investment`. An `.env` created before this variable existed lacks it, so copy the line from `.env.example` or pass `--investment-configuration-ref`.

2. Publish a trade signal:

   ```sh
   docker compose exec app php artisan demo-analytics:publish-trade-signal
   ```

   The command prints `Published trade signal <id>.` By default it publishes a `long` trade signal for `DEMO_A` with confidence `0.75`, a horizon of 5 trading sessions, an expected move from 1.0 to 3.0 percent, an invented reference price of 12.50 EUR, and signal validity of three days. Options choose each value: `--symbol`, `--direction`, `--confidence`, `--horizon-sessions`, `--expected-move-min`, `--expected-move-max`, `--reference-price`, `--currency`, `--valid-until`, and `--investment-configuration-ref`; `--help` describes them. `--twice` publishes the same message twice to exercise duplicate delivery. The command refuses a symbol that does not start with `DEMO_` and any other invalid value, and then publishes nothing. It has no outbox and does not retry: when RabbitMQ does not confirm the message, it reports the error and exits with code 1, and you can run it again.

3. Read the signal outcomes:

   ```sh
   docker compose exec app php artisan demo-analytics:read-signal-outcomes
   ```

   The command prints each signal outcome from `tm.signal-outcomes.demo-analytics` with the trade signal `id`, the outcome, and its details, and then acknowledges it. It says so when the queue is empty.

Until the simulated broker exists, a valid trade signal is accepted at intake and has no signal outcome yet. To see a signal outcome now, address an investment configuration that does not exist; the outcome is `SignalRejected`:

```sh
docker compose exec app php artisan demo-analytics:publish-trade-signal --investment-configuration-ref=demo-unknown-investment
docker compose exec app php artisan demo-analytics:read-signal-outcomes
```

## Quality checks

For CI configuration changes, follow the [three-step verification process](SDLC.md#changes-to-ci-configuration). `make ci-lint` checks workflows, shell scripts, and Compose; GitHub Actions runs it before building the PHP image.

Run `make ci` (or `sh bin/ci`) from the host. It builds the PHP image with PCOV, installs locked Composer dependencies, starts PostgreSQL and RabbitMQ, ensures the separate test database exists, and runs `composer ci` in a disposable PHP container with test configuration. It keeps the existing application key and database data. PCOV is disabled during normal development and enabled for mutation coverage.

The pipeline runs Composer validation and `composer audit --locked`, PHP CS Fixer in check mode, PHPStan with Larastan at level 8, Deptrac, PHPUnit, Behat, and Infection. PHP CS Fixer follows PSR-12 with explicit adjustments matching the existing Pint style. PHPStan also enforces cognitive complexity limits on every path it analyses, through `tomasvotruba/cognitive-complexity`: 9 per function or method and 40 per class, set in `phpstan.neon.dist`. The `spaze/phpstan-disallowed-calls` extension rejects calls from `Domain` and `Application` to functions from installed packages, which Deptrac cannot detect. Its `disallowedMethodCalls` entry reports a call to an aggregate's `state()` outside repository implementations and tests ([ADR-0002](docs/ADR/adr-0002-aggregate-state-for-eloquent-persistence.md)). Infection uses minimum MSI 80% and covered MSI 100%. The full run checks all configured application sources (`app/` and `src/`), including uncovered code, and fails when no mutations are generated. The changed-line run checks only the lines changed since the merge base with `origin/main`. It ignores lines PHPUnit does not run and passes when the changes generate no mutations. These initial thresholds apply to the small skeleton and should be reviewed as business code is introduced.

GitHub Actions runs `make ci` for pull requests, pushes to `main`, and manual dispatch. Pull requests run all checks with Infection on changed lines; pushes to `main` and manual runs include the full Infection run. Docker build layers and Composer downloads are cached between runs. Local `make ci` includes the full Infection run by default. Use `CI_WITH_MUTATION=diff make ci` for the pull-request checks, after `git fetch` so that `origin/main` is current, and `CI_WITH_MUTATION=0 make ci` to skip Infection. `CI_SKIP_BUILD=1` reuses an already built PHP image, as the workflow does after its cached build step. Composer downloads are kept in `storage/framework/cache/composer`.

PHPUnit runs the RabbitMQ integration tests against the `trade-maneuver-testing` vhost. PHPUnit and Infection reports are written to `storage/quality/` and uploaded as workflow artifacts for seven days. Behat runs two suites: `application` checks service health, and `trading` covers trade signal intake through the application commands against the test database. Deptrac enforces the layer rules in `deptrac.yaml` for the Trading context and the application code under the root namespace; [the architecture](docs/architecture.md#laravel-integration) describes them. Run it separately with `docker compose exec app composer analyse:architecture`.

## PostgreSQL

The application connects to host `postgres` on port `5432`. An IDE or another client on the host connects to `127.0.0.1:54320`. The default database and user are `trade_maneuver`, and the local password is `local_trade_maneuver_password` (configured in `.env`).

Tests use a separate database, `trade_maneuver_testing`, created when PostgreSQL is initialized and by `bin/setup`. `phpunit.xml` enforces its name and the `pgsql` driver. `EloquentPostgresTest` verifies that a model can be created, read, updated and deleted against a real PostgreSQL database. `RefreshDatabase` runs migrations in the test database and isolates test data with transactions.

PostgreSQL data stays in the volume after `make down`. Changing the credentials in `.env` does not update the users of an existing cluster, because the PostgreSQL image only initializes an empty volume.

## Backlog and contributions

The task backlog is in `docs/tasks/`. A task with `status: approved` on `main` holds the requirements for its implementation, and a task with `status: done` records what was delivered.

This project has no [license](#license) and accepts no contributions from anyone other than its author. It does not use GitHub Issues, and pull requests from other people will not be merged.

## License

No license has been selected for this project's original code yet, so all rights are reserved. Apart from what the GitHub Terms of Service allow for public repositories, no permission to use, copy, modify, or distribute that code is granted.

## Third-party licenses

Third-party components keep their own licenses.

### Laravel: The MIT License (MIT)

The following copyright and license notice applies only to code originating from Laravel. It does not license this project's original code.

Copyright (c) Taylor Otwell

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
