ARG PHP_IMAGE=php:latest
ARG COMPOSER_IMAGE=composer:latest
ARG ASSETS_IMAGE=node:latest

FROM $ASSETS_IMAGE AS assets
WORKDIR /app
#COPY ./package.json /app/package.json
#COPY ./yarn.lock /app/yarn.lock
#RUN yarn install
#COPY ./frontend/src /app/src
#RUN yarn build

FROM $COMPOSER_IMAGE AS composer

FROM $PHP_IMAGE as vendor
ARG GITHUB_TOKEN
RUN apk add --no-cache git
COPY --from=composer /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY ./backend/composer.json /app/backend/composer.json
COPY ./fuelphp/composer.json /app/fuelphp/composer.json
COPY ./fuelphp/composer.lock /app/fuelphp/composer.lock
RUN if [ -n "$GITHUB_TOKEN" ]; then composer config -g github-oauth.github.com $GITHUB_TOKEN; fi
RUN composer install -d fuelphp --prefer-dist --no-dev --no-scripts --ignore-platform-reqs --optimize-autoloader
COPY ./backend/src /app/backend/src
COPY ./fuelphp/fuel /app/fuelphp/fuel
COPY ./fuelphp/oil /app/fuelphp/oil
RUN rm /app/fuelphp/composer.lock

FROM $PHP_IMAGE

ENV WWWUSER=www-data

RUN docker-php-ext-install \
    bcmath \
    pdo_mysql \
    mysqli \
    opcache \
    pcntl

# PHP settings
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php-custom.ini $PHP_INI_DIR/conf.d/custom.ini
COPY docker/php-opcache.ini $PHP_INI_DIR/conf.d/opcache.ini

# PHP-FPM settings
RUN sed -i 's,^;log_level =.*$,log_level = error,' "$PHP_INI_DIR/../php-fpm.conf"
COPY docker/php-fpm-docker.conf $PHP_INI_DIR/../php-fpm.d/zzz-docker.conf

COPY --from=vendor --chown=${WWWUSER}:${WWWUSER} /app /app
COPY --chown=${WWWUSER}:${WWWUSER} ./fuelphp/public /app/fuelphp/public
#COPY --from=assets --chown=${WWWUSER}:${WWWUSER} /app/public/assets /app/public/assets

WORKDIR /app
