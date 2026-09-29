DOCKER_COMPOSE ?= docker compose
PHP_SERVICE ?= api

.PHONY: analyse build crawl-dispatch crawl-source crawl-tick down install lint migrate migrate-fresh scheduler shell test up validate worker

build:
	$(DOCKER_COMPOSE) build

install:
	$(DOCKER_COMPOSE) run --rm $(PHP_SERVICE) composer install

shell:
	$(DOCKER_COMPOSE) run --rm $(PHP_SERVICE) bash

validate:
	$(DOCKER_COMPOSE) run --rm $(PHP_SERVICE) composer validate

lint:
	$(DOCKER_COMPOSE) run --rm $(PHP_SERVICE) composer lint

test:
	$(DOCKER_COMPOSE) run --rm $(PHP_SERVICE) composer test

analyse:
	$(DOCKER_COMPOSE) run --rm $(PHP_SERVICE) composer analyse

migrate:
	$(DOCKER_COMPOSE) run --rm $(PHP_SERVICE) php artisan migrate --force

migrate-fresh:
	$(DOCKER_COMPOSE) run --rm $(PHP_SERVICE) php artisan migrate:fresh --force

up:
	@test -f .env || cp .env.example .env
	$(DOCKER_COMPOSE) up -d postgres mongo elasticsearch flaresolverr
	$(DOCKER_COMPOSE) up -d api worker worker-2 scheduler

down:
	$(DOCKER_COMPOSE) down

worker:
	$(DOCKER_COMPOSE) up -d worker worker-2

scheduler:
	$(DOCKER_COMPOSE) run --rm scheduler

crawl-tick:
	$(DOCKER_COMPOSE) run --rm $(PHP_SERVICE) php artisan crawl:tick

crawl-dispatch:
	$(DOCKER_COMPOSE) run --rm $(PHP_SERVICE) php artisan crawl:dispatch

crawl-source:
	$(DOCKER_COMPOSE) run --rm $(PHP_SERVICE) php artisan crawl:source $(SITE) --dispatch --limit=$(or $(LIMIT),20)
