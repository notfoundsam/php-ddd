ARG PHP_IMAGE=php:latest
ARG COMPOSER_IMAGE=composer:latest

FROM $COMPOSER_IMAGE AS composer

FROM $PHP_IMAGE
ARG GITHUB_TOKEN
RUN apk add --no-cache git
COPY --from=composer /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY ./fuelphp/phpcs.xml /app/phpcs.xml
COPY ./fuelphp/composer.json /app/composer.json
COPY ./fuelphp/composer.lock /app/composer.lock
RUN if [ -n "$GITHUB_TOKEN" ]; then composer config -g github-oauth.github.com $GITHUB_TOKEN; fi
RUN composer install -d fuelphp --prefer-dist --no-scripts --ignore-platform-reqs --optimize-autoloader
COPY ./backend /app/backend
