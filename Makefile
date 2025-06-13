install:
	docker compose build
	docker compose run --rm fuelphp composer -d fuelphp install
build:
	docker compose build
composer-autoload:
	docker compose run --rm fuelphp composer -d fuelphp dump-autoload
up:
	docker compose up -d
stop:
	docker compose stop
sh:
	docker compose exec fuelphp sh
linter:
	docker compose run --rm fuelphp fuelphp/fuel/vendor/bin/phpcs --standard=backend/phpcs.xml backend
test-unit:
	docker compose run --rm fuelphp fuelphp/fuel/vendor/bin/phpunit backend/tests
