DC      ?= docker compose
APP     ?= app

.PHONY: help start up stop stop build install composer-install logs shell test phpcs phpstan check seo-urls

help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"; printf "Targets:\n"} /^[a-zA-Z_-]+:.*##/ {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

start up: ## Start the stack in the background
	$(DC) up -d

stop: ## Stop and remove the stack
	$(DC) stop

build: ## Build (or rebuild) the Docker images
	$(DC) build

composer-install: ## Run `composer install` inside the app container
	$(DC) run --rm $(APP) composer install

composer: ## Run an arbitrary composer command, e.g. `make composer CMD="require foo/bar"`
	$(DC) run --rm $(APP) composer $(CMD)

logs: ## Tail logs from all services
	$(DC) logs -f

shell: ## Open a shell in the app container
	$(DC) exec $(APP) sh

test: ## Run the PHPUnit test suite inside the app container
	$(DC) run --rm $(APP) vendor/bin/phpunit

phpcs: ## Run PHP_CodeSniffer using phpcs.xml inside the app container
	$(DC) run --rm $(APP) vendor/bin/phpcs

phpstan: ## Run PHPStan analysis using phpstan.neon inside the app container
	$(DC) run --rm $(APP) vendor/bin/phpstan analyse

check: ## Run all checks: phpcs, phpstan, and tests
	$(MAKE) phpcs && $(MAKE) phpstan && $(MAKE) test

seo-urls: ## Stamp public/sitemap.xml & robots.txt with APP_URL from .env
	$(DC) run --rm $(APP) php bin/update-seo-urls.php
