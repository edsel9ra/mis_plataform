.PHONY: env up down build restart logs wait backend frontend matching shell migrate seed fresh test-backend test-frontend test-matching queue reverb storage cache meili npm-install composer-install production-config

env:
	bash setup.sh --env-only

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

restart: down up

logs:
	docker compose logs -f

wait:
	bash wait-for-services.sh

backend:
	docker compose exec backend sh

frontend:
	docker compose exec frontend sh

matching:
	docker compose exec matching sh

shell:
	docker compose exec backend php artisan tinker

migrate:
	docker compose exec backend php artisan migrate

seed:
	docker compose exec backend php artisan db:seed

fresh:
	docker compose exec backend php artisan migrate:fresh --seed

test-backend:
	docker compose exec backend php artisan test

test-frontend:
	docker compose exec frontend pnpm test

test-matching:
	docker compose exec matching python -m pytest -p no:cacheprovider

queue:
	docker compose exec backend php artisan queue:work

reverb:
	docker compose exec backend php artisan reverb:start

storage:
	docker compose exec backend php artisan storage:link

cache:
	docker compose exec backend php artisan optimize

meili:
	docker compose exec backend php artisan scout:import "App\Models\User"

npm-install:
	docker compose exec frontend pnpm install --frozen-lockfile

composer-install:
	docker compose exec backend composer install --prefer-dist --no-interaction --no-progress

production-config:
	docker compose --env-file .env.production -f docker-compose.production.yml config --quiet
