DC = docker compose
PHP = $(DC) exec -u www-data api

.PHONY: init up down build logs test shell migrate fresh

## First run: env files, images, dependencies, app key, database, storage link.
init:
	@test -f backend/.env || cp backend/.env.example backend/.env
	@test -f frontend/.env || cp frontend/.env.example frontend/.env
	$(DC) build
	$(DC) up -d
	$(PHP) composer install --no-interaction
	@grep -q '^APP_KEY=base64' backend/.env || $(PHP) php artisan key:generate
	$(PHP) php artisan migrate --force
	$(PHP) php artisan storage:link --force
	@echo "Frontend: http://localhost:5173  API: http://localhost:8080/api/images"

up:
	$(DC) up -d

down:
	$(DC) down

build:
	$(DC) build

logs:
	$(DC) logs -f

test:
	$(PHP) php artisan test

shell:
	$(PHP) bash

migrate:
	$(PHP) php artisan migrate

fresh:
	$(PHP) php artisan migrate:fresh
