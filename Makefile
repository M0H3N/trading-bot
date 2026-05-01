# Docker service names for Laravel, Redis, and MySQL containers
COMPOSE_FILE=compose.dev.yml
PHP_FPM_CONTAINER=php-fpm
FRANKEN_PHP_CONTAINER=franken-php
REDIS_CONTAINER=redis
MYSQL_CONTAINER=mysql

# Kubernetes context and namespaces
CONTEXT=at1-k8s-stage
NAMESPACES=wallgold-staging-1 wallgold-staging-2 wallgold-staging-3 wallgold-staging-4

# Default target
.DEFAULT_GOAL := help

# Load .env into environment variables
ifneq (,$(wildcard .env))
	include .env
	export
endif

# -------------------------
# 🍃 Laravel Commands
# -------------------------

.PHONY: boost
boost: ## Run Laravel Boost command
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) php artisan boost:mcp

mcp: ## Run Wallgold MCP command
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) php artisan mcp:serve


.PHONY: art
art: ## Run Laravel Artisan command
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) php artisan $(CMD)

.PHONY: test
test: ## Run Laravel tests with optional arguments
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) sh -c 'XDEBUG_MODE=coverage php artisan test --env=testing $(CMD)'

.PHONY: fullTest
fullTest: ## Run All Laravel tests in parallel mode with optional arguments
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) sh -c 'XDEBUG_MODE=coverage php artisan test --parallel --processes=11 --env=testing $(CMD)'



.PHONY: docs
docs: ## Generate Laravel documentation
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) php -d memory_limit=512M artisan scribe:generate --config=scribe

.PHONY: docs-admin
docs-admin: ## Generate Laravel documentation
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) php -d memory_limit=512M artisan scribe:generate --config=scribe-admin

.PHONY: style
style: ## style code
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) ./vendor/bin/pint

.PHONY: lint
lint: ## style code
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) ./vendor/bin/phpstan --memory-limit=2G

.PHONY: migrate
migrate: ## Run Laravel migrations
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) php artisan migrate

.PHONY: migrate-rollback
migrate-rollback: ## Run Laravel migrations
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) php artisan migrate:rollback

.PHONY: seed
seed: ## Run database seeders
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) php artisan db:seed

.PHONY: tinker
tinker: ## Open Laravel Tinker
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) php artisan tinker

.PHONY: queue
queue: ## Start Laravel queue worker
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) php artisan queue:work

.PHONY: horizon
horizon: ## Start Laravel Horizon
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) php artisan horizon

.PHONY: optimize
optimize: ## Optimize Laravel
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) php artisan optimize

.PHONY: optimize-clear
optimize-clear: ## Clear config, route, view, cache
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) php artisan config:clear
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) php artisan route:clear
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) php artisan view:clear
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) php artisan cache:clear

.PHONY: routes-admin
routes-admin: ## Show Laravel route list
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) php artisan route:list --path=api/admin

.PHONY: routes-user
routes-user: ## Show Laravel route list
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) php artisan route:list --except-path=api/admin

.PHONY: api-attributes
api-attributes: ## Show Laravel API attributes
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) php artisan api:attributes

.PHONY: logs-all
logs-all: ## Tail all Laravel logs
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) sh -c 'tail -f storage/logs/*.log'
# -------------------------
# 🐘 PHP Commands
# -------------------------

.PHONY: php
php: ## Run a PHP script inside container
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) php $(ARGS)

.PHONY: composer
composer: ## Run composer in app container
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) composer $(ARGS)

.PHONY: dump-autoload
dump-autoload: ## Run composer dump-autoload
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) composer dump-autoload

.PHONY: bash
bash: ## Bash into the app container
	@docker compose -f $(COMPOSE_FILE) exec $(PHP_FPM_CONTAINER) sh

.PHONY: logs
logs: ## Show container logs
	@docker compose -f $(COMPOSE_FILE) logs -f $(PHP_FPM_CONTAINER)

# -------------------------
# 🧑‍💻 Redis Commands
# -------------------------

.PHONY: redis-cli
redis-cli: ## Connect to Redis using redis-cli
	@docker compose -f $(COMPOSE_FILE) exec $(REDIS_CONTAINER) redis-cli

# -------------------------
# 🍇 MySql Commands
# -------------------------

.PHONY: mysql
mysql: ## Connect to MySQL container with bash
	@docker compose -f $(COMPOSE_FILE) exec $(MYSQL_CONTAINER) bash

.PHONY: db
db: ## Connect to MySql using mysql command
	@docker compose -f $(COMPOSE_FILE) exec $(MYSQL_CONTAINER) mysql -u ${DB_USERNAME} -p${DB_PASSWORD} ${DB_DATABASE}

# -------------------------
# 🛠️ Docker Helpers
# -------------------------

.PHONY: up
up: ## Start containers
	@docker compose -f $(COMPOSE_FILE) up -d

.PHONY: down
down: ## Stop containers
	@docker compose -f $(COMPOSE_FILE) down

.PHONY: restart
restart: ## Restart app container
	@docker compose -f $(COMPOSE_FILE) restart $(PHP_FPM_CONTAINER)

.PHONY: build
build: ## Build Docker containers
	@docker compose -f $(COMPOSE_FILE) build

.PHONY: rebuild
rebuild: ## Rebuild and restart all containers
	@docker compose -f $(COMPOSE_FILE) down
	@docker compose -f $(COMPOSE_FILE) build
	@docker compose -f $(COMPOSE_FILE) up -d

.PHONY: ps
ps: ## List running containers
	@docker compose -f $(COMPOSE_FILE) ps

# -------------------------
# 🛠️ Staging k8s Helpers
# -------------------------

.PHONY: k8s-contexts
k8s-contexts: ## Run kubectl contexts
	@kubectl config get-contexts

.PHONY: k8s-set-ns
k8s-set-ns: ## Set namespace for the current context (optionally provide NS=namespace)
	@echo "📌 Current namespace: $$(kubectl config view --minify --output 'jsonpath={..namespace}')"
	@if [ -z "$(NS)" ]; then \
		echo "📦 Available namespaces:"; \
		i=1; for ns in $(NAMESPACES); do echo "  $$i) $$ns"; i=$$((i+1)); done; \
		read -p "Select namespace number: " choice; \
		NS=$$(echo "$(NAMESPACES)" | tr ' ' '\n' | sed -n "$${choice}p"); \
		if [ -z "$$NS" ]; then \
			echo "❌ Invalid selection"; \
			exit 1; \
		fi; \
	else \
		if ! echo "$(NAMESPACES)" | grep -qw "$(NS)"; then \
			echo "❌ Invalid namespace: $(NS)"; \
			echo "Available namespaces:"; \
			echo "  $(NAMESPACES)"; \
			exit 1; \
		fi; \
	fi; \
	kubectl config set-context $(CONTEXT) --namespace=$$NS; \
	echo "✅ Context '$(CONTEXT)' set to namespace '$$NS'"

.PHONY: k8s-current-ns
k8s-current-ns: ## Show current namespace of active context
	@echo "📌 Current context: $$(kubectl config current-context)"
	@echo "📌 Current namespace: $$(kubectl config view --minify --output 'jsonpath={..namespace}')"

.PHONY: k8s-deployments
k8s-deployments: ## Get deployments in the current namespace
	@kubectl get deployments

.PHONY: k8s-pods
k8s-pods: ## Get pods in the current namespace
	@kubectl get pods -o wide

.PHONY: k8s-exec
k8s-exec: ## Exec into a pod interactively
	@echo "📦 Getting pods in current namespace..."; \
	kubectl get pods --no-headers -o custom-columns=":metadata.name" | nl -w2 -s') '; \
	read -p "Select pod number: " choice; \
	POD=$$(kubectl get pods --no-headers -o custom-columns=":metadata.name" | sed -n "$${choice}p"); \
	if [ -z "$$POD" ]; then \
		echo "❌ Invalid selection"; \
		exit 1; \
	fi; \
	echo "📦 Getting containers in pod '$$POD'..."; \
	containers=$$(kubectl get pod $$POD -o jsonpath='{.spec.containers[*].name}'); \
	echo "📦 Available containers:"; \
	i=1; for container in $$containers; do echo "  $$i) $$container"; i=$$((i+1)); done; \
	read -p "Select container number: " container_choice; \
	CONTAINER=$$(echo "$$containers" | tr ' ' '\n' | sed -n "$${container_choice}p"); \
	if [ -z "$$CONTAINER" ]; then \
		echo "❌ Invalid container selection"; \
		exit 1; \
	fi; \
	echo "🔌 Exec into pod '$$POD' container '$$CONTAINER'..."; \
	kubectl exec -it $$POD -c $$CONTAINER -- bash

.PHONY: k8s-logs
k8s-logs: ## Tail logs from a selected pod and container
	@pods=$$(kubectl get pods --no-headers -o custom-columns=":metadata.name"); \
	select pod in $$pods; do \
		[ -n "$$pod" ] || { echo "❌ Invalid selection"; exit 1; }; \
		containers=$$(kubectl get pod $$pod -o jsonpath='{.spec.containers[*].name}'); \
		echo "📦 Containers in pod $$pod:"; \
		select container in $$containers; do \
			[ -n "$$container" ] || { echo "❌ Invalid selection"; exit 1; }; \
			kubectl logs -f $$pod -c $$container; \
			break; \
		done; \
		break; \
	done

# -------------------------
# 📘 Help
# -------------------------
#
.PHONY: help
help: ## Show this help message
	@echo "Available commands:"
	@awk -F':.*## ' '/^[a-zA-Z0-9_-]+:.*## / { printf "  \033[36m%-25s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST) | sort
