FROM composer:2.8.8 AS composer

FROM php:8.1-fpm-alpine

RUN docker-php-ext-install \
    bcmath \
    pdo_mysql \
    pcntl

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del -f .build-deps

# Composer settings
RUN mkdir /.config && chmod 775 /.config
COPY --from=composer /usr/bin/composer /usr/bin/composer

# PHP settings
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Xdebug settings
COPY xdebug-custom.ini $PHP_INI_DIR/conf.d/xdebug-custom.ini

WORKDIR /app
