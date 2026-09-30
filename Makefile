DOCKER_COMPOSE ?= docker compose
PHP_SERVICE ?= api

# Profile flag groups (repeat --profile per value: comma form is not parsed).
PROFILE_ALL = --profile app --profile data --profile openobserve --profile flare --profile control
PROFILE_CRAWLER = --profile app --profile flare
PROFILE_CONTROL = --profile app --profile control

# Worker instances come from .env (JVMETA_WORKER_INSTANCES); override on the
# command line wins: `make up JVMETA_WORKER_INSTANCES=4`.
JVMETA_WORKER_INSTANCES ?= 2
ifneq ($(strip $(shell grep -E '^JVMETA_WORKER_INSTANCES=' .env 2>/dev/null)),)
JVMETA_WORKER_INSTANCES := $(shell grep -E '^JVMETA_WORKER_INSTANCES=' .env 2>/dev/null | head -1 | cut -d= -f2-)
endif

APP_SERVICES = api worker
CONTROL_SERVICES = scheduler mcp
DATA_SERVICES = postgres mongo elasticsearch
OBS_SERVICES = openobserve
FLARE_SERVICES = flaresolverr
LOCAL_SERVICES = $(APP_SERVICES) $(DATA_SERVICES) $(OBS_SERVICES) $(FLARE_SERVICES) $(CONTROL_SERVICES)

.PHONY: analyse build crawl-dispatch crawl-source crawl-tick down install lint migrate migrate-fresh scheduler shell test up up-control up-crawler up-ext up-node validate worker

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

# Local full stack: all bundled services in Docker (develop default).
up:
	@test -f .env || cp .env.example .env
	$(DOCKER_COMPOSE) $(PROFILE_ALL) up -d --scale worker=$(JVMETA_WORKER_INSTANCES) $(LOCAL_SERVICES)

# External mode: only app services run here; data/obs/flare point at external endpoints via .env.
up-ext:
	$(DOCKER_COMPOSE) --profile app up -d --scale worker=$(JVMETA_WORKER_INSTANCES) $(APP_SERVICES)

# Production crawler instance: app + workers + flaresolverr (no scheduler/mcp).
up-crawler:
	$(DOCKER_COMPOSE) $(PROFILE_CRAWLER) up -d --scale worker=$(JVMETA_WORKER_INSTANCES) $(APP_SERVICES) $(FLARE_SERVICES)

# Production control instance (ONE only): app + workers + scheduler + mcp.
up-control:
	$(DOCKER_COMPOSE) $(PROFILE_CONTROL) up -d --scale worker=$(JVMETA_WORKER_INSTANCES) $(APP_SERVICES) $(CONTROL_SERVICES)

# Production single node: app + workers + scheduler + mcp + flaresolverr (data/obs external).
up-node:
	$(DOCKER_COMPOSE) --profile app --profile flare --profile control up -d --scale worker=$(JVMETA_WORKER_INSTANCES) $(APP_SERVICES) $(FLARE_SERVICES) $(CONTROL_SERVICES)

down:
	$(DOCKER_COMPOSE) $(PROFILE_ALL) down

worker:
	$(DOCKER_COMPOSE) --profile app up -d --scale worker=$(JVMETA_WORKER_INSTANCES) worker

scheduler:
	$(DOCKER_COMPOSE) run --rm scheduler

crawl-tick:
	$(DOCKER_COMPOSE) run --rm $(PHP_SERVICE) php artisan crawl:tick

crawl-dispatch:
	$(DOCKER_COMPOSE) run --rm $(PHP_SERVICE) php artisan crawl:dispatch

crawl-source:
	$(DOCKER_COMPOSE) run --rm $(PHP_SERVICE) php artisan crawl:source $(SITE) --dispatch --limit=$(or $(LIMIT),20)