# Trade Maneuver

> [!IMPORTANT]
> Trade Maneuver is a simulation, built as a portfolio and educational project. Its broker, brokerage accounts, instruments, prices, and amounts are fictional, and so are the trade signals of its local flow. It has no connection to a real market, real funds, or a real brokerage account.

Trade Maneuver will be an execution microservice: it receives trade signals from analytics applications, evaluates them against its domain rules, places orders with a simulated, fictional broker, and reports their outcomes. Integration with a real broker is out of scope. See [the integration overview](docs/integration.md) for an outline of the planned solution.

The stack is Laravel, PHP, and PostgreSQL. Versions are defined in `composer.json`, `compose.yaml`, and `docker/php/Dockerfile`. The local environment runs in Docker Compose; the host only needs Docker with Compose v2+ and a `sh` shell. Make is optional.

## What is simulated

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

The script prepares everything the application needs, starts all services, and waits until they are healthy. You can run it again: it keeps the existing application key and the data in the database.

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
| `queue` | Separate `queue:work` worker, which also evaluates accepted trade signals and publishes signal outcomes from the outbox to RabbitMQ | internal |
| `scheduler` | Separate `schedule:work` process | internal |
| `node` | Node.js, Vite with hot reload | `127.0.0.1:5173` |

The project directory is mounted into the containers, so editing PHP does not require rebuilding the image. After changing job code run `docker compose restart queue`; after changing the schedule run `docker compose restart scheduler`; after changing the consumer run `docker compose restart trade-signal-consumer`.

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

Without Make, run Artisan and Composer through `docker compose exec app` and npm through `docker compose exec node`. [The development reference](docs/development.md) covers the environment, PostgreSQL, RabbitMQ, failure recovery, and the CI quality checks in more detail.

## Local trade signal flow

The demo analytics application is a fictional stand-in for an analytics application, so the flow can run locally without a real one: its trade signals name fictional instruments on the fictional exchange `DEMO`, carry invented values, and recommend nothing. Its registry entry and producer user exist only in the local and CI configuration.

1. Create the investment configuration that authorizes it:

   ```sh
   docker compose exec app php artisan trading:create-investment-configuration demo-investment \
       --currency=EUR --brokerage-account=DEMO-ACCOUNT-1 --order-amount=200.00 \
       --limit-price-tolerance=1.50 --authorized-analytics-application=demo-analytics
   ```

   The demo analytics application's trade signals address `demo-investment`, the reference set in `DEMO_ANALYTICS_INVESTMENT_CONFIGURATION_REF`.

2. Publish a trade signal:

   ```sh
   docker compose exec app php artisan demo-analytics:publish-trade-signal
   ```

   `--help` lists the options that set each value of the trade signal.

3. Read the signal outcomes:

   ```sh
   docker compose exec app php artisan demo-analytics:read-signal-outcomes
   ```

   The command prints each signal outcome with the trade signal `id`, the outcome, and its details, and then acknowledges it.

Each of these trade signals ends as its comment says. [The contract rules](docs/integration.md#3-contract-tmtrade-signalv1) describe evaluation, and `src/Trading/Infrastructure/Resources/config.php` holds the simulated broker's catalogue of fictional instruments.

```sh
# SignalRejected: the investment configuration does not exist.
docker compose exec app php artisan demo-analytics:publish-trade-signal --investment-configuration-ref=demo-unknown-investment
# SignalDeclined with the reason UnsupportedDirection: Trade Maneuver only buys.
docker compose exec app php artisan demo-analytics:publish-trade-signal --direction=short
# No signal outcome: DEMO_A passes evaluation and leads to an order awaiting approval.
docker compose exec app php artisan demo-analytics:publish-trade-signal --symbol=DEMO_A
# SignalDeclined with the reason OrderAmountTooSmall: at 200.00 EUR and 1.50 %, the limit price 203.00 EUR exceeds the order amount.
docker compose exec app php artisan demo-analytics:publish-trade-signal --symbol=DEMO_A --reference-price=200.00
# SignalDeclined with the reason UnsupportedCurrency: DEMO_G is quoted in USD, the investment configuration in EUR.
docker compose exec app php artisan demo-analytics:publish-trade-signal --symbol=DEMO_G --currency=USD
# SignalDeclined with the reason InstrumentUnavailable: DEMO_H is unavailable.
docker compose exec app php artisan demo-analytics:publish-trade-signal --symbol=DEMO_H
# SignalDeclined with the reason InstrumentUnavailable: DEMO_X is unknown to the simulated broker.
docker compose exec app php artisan demo-analytics:publish-trade-signal --symbol=DEMO_X
# SignalRejected on referencePrice.currency: DEMO_A is quoted in EUR, not USD.
docker compose exec app php artisan demo-analytics:publish-trade-signal --symbol=DEMO_A --currency=USD
docker compose exec app php artisan demo-analytics:read-signal-outcomes
```

The command refuses a validity that ends before the trade signal is published. To see `SignalDeclined` with the reason `SignalExpired`, stop the queue worker, publish a trade signal whose validity ends a few seconds later, and start the worker after that time:

```sh
docker compose stop queue
docker compose exec app php artisan demo-analytics:publish-trade-signal --valid-until="$(docker compose exec -T app php -r "echo gmdate('Y-m-d\TH:i:s\Z', time() + 5);")"
sleep 10
docker compose start queue
docker compose exec app php artisan demo-analytics:read-signal-outcomes
```

## Quality checks

Before opening a pull request, run `git fetch` so that `origin/main` is current, then `CI_WITH_MUTATION=diff make ci` for the pull-request checks. `make ci` alone runs the complete pipeline, including the full Infection run. For CI configuration changes, follow the [three-step verification process](SDLC.md#changes-to-ci-configuration). [The development reference](docs/development.md#ci-quality-checks) lists the checks, their configuration, the Infection variants, and the reports.

## Backlog and contributions

Follow the [SDLC contract](SDLC.md) for task preparation, approval, review, and delivery. It distinguishes the current CI pipeline from planned automation and CD.

The task backlog is in `docs/tasks/`. A task with `status: approved` on `main` holds the requirements for its implementation, and a task with `status: done` records what was delivered.

This project has no [license](#license) and accepts no contributions from anyone other than its author. It does not use GitHub Issues, and pull requests from other people will not be merged.

## License

No license has been selected for this project's original code yet, so all rights are reserved. Apart from what the GitHub Terms of Service allow for public repositories, no permission to use, copy, modify, or distribute that code is granted.

## Third-party licenses

Third-party components keep their own licenses. [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md) holds Laravel's MIT notice and says where to find the licenses of the dependencies.
