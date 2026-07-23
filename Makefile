.PHONY: up down build restart logs backend frontend matching migrate seed test

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

restart: down up

logs:
	docker compose logs -f

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
	docker compose exec frontend npx playwright test

queue:
	docker compose exec backend php artisan queue:work

reverb:
	docker compose exec backend php artisan reverb:start

storage:
	docker compose exec backend php artisan storage:link

key:
	docker compose exec backend php artisan key:generate

cache:
	docker compose exec backend php artisan optimize

meili:
	docker compose exec backend php artisan scout:import "App\Models\User"

npm-install:
	docker compose exec frontend pnpm install

composer-install:
	docker compose exec backend composer install
