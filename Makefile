.PHONY: setup up down logs shell migrate test build format

setup:
	./bin/setup

up:
	docker compose up -d --wait

down:
	docker compose down
	rm -f public/hot public/fonts-manifest.dev.json

logs:
	docker compose logs -f

shell:
	docker compose exec app sh

migrate:
	docker compose exec app php artisan migrate --no-interaction

test:
	docker compose exec app php artisan test --compact

build:
	docker compose run --rm --no-deps node npm run build

format:
	docker compose exec app vendor/bin/pint
