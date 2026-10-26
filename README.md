# trade-maneuver

Laravel 13, PHP 8.5 and PostgreSQL 18 with Eloquent. The local environment runs in Docker Compose; the host only needs Docker with Compose v2+ and a `sh` shell. Make is optional.

Laravel 13 is the current stable line, with security support until March 17, 2028: [support policy](https://laravel.com/docs/13.x/releases). Exact application dependency versions are recorded in `composer.lock` and `package-lock.json`. Images are pinned to PHP, PostgreSQL and Node.js release lines, and Nginx follows the `stable-alpine` channel.

## First run

```sh
./bin/setup
# or: make setup
```

The script creates `.env`, builds the PHP image, matches UID/GID to the owner of the project directory (including rootless Docker), installs dependencies, generates a missing `APP_KEY`, runs migrations and builds the frontend. It then starts all services and waits until they are healthy. It is safe to run again; it keeps the existing key and the data in the database.

Application: **http://localhost:8080**. Laravel health check: http://localhost:8080/up.

If you need different ports, copy `.env.example` to `.env` before starting and change `APP_PORT`, `APP_URL`, `VITE_PORT` or `FORWARD_DB_PORT`. `DB_PORT=5432` is the port inside the Compose network and stays unchanged. `VITE_HOST=localhost` is the host name the browser uses to reach Vite.

## Services

| Container | Purpose | Host access |
| --- | --- | --- |
| `nginx` | HTTP, static files, forwarding requests to PHP-FPM | `127.0.0.1:8080` |
| `app` | PHP 8.5 FPM, Artisan, Composer, Eloquent and `pdo_pgsql` | via Nginx |
| `postgres` | PostgreSQL 18, persistent `postgres_data` volume | `127.0.0.1:54320` |
| `queue` | Separate `queue:work` worker | internal |
| `scheduler` | Separate `schedule:work` process | internal |
| `node` | Node.js 24 LTS, Vite with hot reload | `127.0.0.1:5173` |

The project directory is mounted into the containers, so editing PHP does not require rebuilding the image. After changing job code run `docker compose restart queue`; after changing the schedule run `docker compose restart scheduler`.

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

`make down` also removes the `public/hot` marker, so after Vite is stopped Laravel can use previously built assets. When running `docker compose down` manually and working without Vite, remove that marker yourself. The standard `composer run dev` and `composer run setup` scripts come from the Laravel skeleton; in this environment use `make up` and `make setup`, which manage the separate containers.

## PostgreSQL and Eloquent

From the application you connect to host `postgres`, port `5432`. From an IDE or a client on the host: `127.0.0.1:54320`. The default database and user are `trade_maneuver`, and the local password is `local_trade_maneuver_password` (configured in `.env`).

Eloquent is Laravel's default ORM. The ready-made `App\Models\User` model, its factory and migration use the `pgsql` connection; create further models with the standard Artisan commands. PHP extensions also include `bcmath`, `intl`, `zip` and `pcntl`.

Tests use a separate database, `trade_maneuver_testing`, created when PostgreSQL is initialized and by `bin/setup`. `phpunit.xml` enforces its name and the `pgsql` driver. `EloquentPostgresTest` verifies that a model can be created, read, updated and deleted against a real PostgreSQL database. `RefreshDatabase` runs migrations in the test database and isolates test data with transactions.

PostgreSQL data stays in the volume after `make down`. Changing the credentials in `.env` does not update the users of an existing cluster, because the PostgreSQL image only initializes an empty volume.

## Third-party licenses

A license for this project's original code has not yet been selected. Third-party components retain their respective licenses.

### Laravel — The MIT License (MIT)

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
