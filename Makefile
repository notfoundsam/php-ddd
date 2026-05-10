install:
	@$(MAKE) dns-mapping
	@$(MAKE) setup-ssl
	docker compose build
	docker compose run --rm fuelphp composer -d backend install
	docker compose run --rm fuelphp composer -d fuelphp install
	docker compose run --rm laravel composer -d laravel install
	@$(MAKE) frontend-install
frontend-install:
	docker compose run --rm frontend npm install --no-audit --no-fund
frontend-build:
	docker compose run --rm frontend npm run build
build:
	docker compose build
composer-autoload:
	docker compose run --rm fuelphp composer -d backend dump-autoload
	docker compose run --rm fuelphp composer -d fuelphp dump-autoload
	docker compose run --rm laravel composer -d laravel dump-autoload
up:
	docker compose up -d
	@echo "⏳ Waiting for services to start..."
	@sleep 3
	@echo "🌐 Opening dashboard in browser..."
	@open https://dashboard.php-ddd.test || true
open:
	@open https://dashboard.php-ddd.test
stop:
	docker compose stop
lint:
	docker compose run --rm fuelphp backend/vendor/bin/phpcs --standard=backend/phpcs.xml backend
phpstan:
	docker compose run --rm fuelphp backend/vendor/bin/phpstan analyse -c backend/phpstan.neon
test-unit:
	docker compose run --rm fuelphp backend/vendor/bin/phpunit backend/tests
	docker compose run --rm --workdir /app/fuelphp/fuel/core fuelphp /app/backend/vendor/bin/phpunit -c phpunit.xml --testsuite app
dns-mapping:
	./dev-tools/dns-mapping.sh
setup-ssl:
	./dev-tools/ssl-setup.sh
cleanup:
	./dev-tools/cleanup.sh
clean:
	@echo "🧹 Cleaning up Docker resources..."
	docker compose down -v
	@echo "✅ Docker containers and volumes removed"
