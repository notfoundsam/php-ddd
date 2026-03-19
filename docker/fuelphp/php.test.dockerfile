ARG PHP_IMAGE=php:latest
ARG COMPOSER_IMAGE=composer:latest

FROM $COMPOSER_IMAGE AS composer

FROM $PHP_IMAGE
ARG GITHUB_TOKEN
RUN apk add --no-cache git
COPY --from=composer /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY ./backend/composer.json /app/backend/composer.json
RUN composer install -d backend --prefer-dist --no-scripts --ignore-platform-reqs --optimize-autoloader
COPY ./backend /app/backend
