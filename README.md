# FuelPHP to Laravel Migration with Domain-Driven Design

This project demonstrates how to **gradually migrate** from an older PHP framework (FuelPHP) to a modern framework (Laravel) by applying **Domain-Driven Design (DDD)** principles.

## Overview

- **Legacy Framework**: FuelPHP (PHP 7.4)
- **Target Framework**: Laravel 10 (PHP 8.1)
- **Shared Domain Layer**: `backend/` — framework-agnostic, pure PHP 7.4+

Both frameworks run side by side in separate Docker containers, sharing the same domain layer and database. A single nginx instance routes traffic by URL path — migrated routes go to Laravel, everything else falls through to FuelPHP.

## Project Structure

```
php-ddd/
├── backend/           # Shared domain layer (Composer package: app/backend)
│   ├── src/           # Bounded contexts: Crm/, Marketing/, SharedKernel/
│   ├── tests/         # Unit tests and fixtures
│   ├── phpcs.xml      # Linting rules (PSR-12 + Slevomat)
│   └── composer.json   # Own dependencies (dev tools only)
├── fuelphp/           # Legacy FuelPHP application (PHP 7.4)
├── laravel/           # New Laravel application (PHP 8.1)
├── docker/            # Production Docker configs
├── dev-tools/         # Development Docker configs, SSL, DNS, dashboard
└── docker-compose.yml
```

### Backend Package

The `backend/` directory is a standalone Composer package (`app/backend`) that both frameworks include via a path repository:

```json
"repositories": [{ "type": "path", "url": "../backend" }],
"require": { "app/backend": "@dev" }
```

Composer creates a symlink, so changes in `backend/` are immediately available to both frameworks.

## Development Commands

```bash
# Initial setup (DNS, SSL, build, install all dependencies)
make install

# Start/stop services
make up
make stop

# Run backend linter (PSR-12 + Slevomat)
make lint

# Run backend unit tests
make test-unit

# Rebuild containers
make build
```

## Routing

Traffic routing is handled by nginx in `dev-tools/nginx_default.conf`. Migrated routes are added to a single regex:

```nginx
# Add migrated paths to the regex
location ~ ^/(laravel|api/v2|admin|users) {
    fastcgi_pass laravel:9000;
    ...
}
```

All other requests fall through to FuelPHP. As migration progresses, add more paths to the regex. When complete, flip the default to Laravel and remove FuelPHP.

## Migration Strategy

1. **Shared Domain Layer** — business logic lives in `backend/`, framework-agnostic
2. **Route-by-Route Migration** — move one endpoint at a time from FuelPHP to Laravel
3. **Single Domain** — both frameworks serve `php-ddd.test`, nginx routes by path
4. **Shared Database** — both apps connect to the same MySQL instance
5. **Finalize** — retire FuelPHP once all routes are migrated, then upgrade `backend/` to PHP 8.1+

## License

This repository is provided under the MIT License. See [LICENSE](LICENSE) for details.
