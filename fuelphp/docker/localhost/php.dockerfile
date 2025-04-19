FROM composer:2.8.8 AS composer

FROM php:7.4.33-fpm-alpine3.16

RUN docker-php-ext-install \
    bcmath \
    pdo_mysql \
    mysqli \
    opcache

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install xdebug-3.1.6 \
    && docker-php-ext-enable xdebug \
    && apk del -f .build-deps

# Composer settings
RUN mkdir /.config && chmod 777 /.config
COPY --from=composer /usr/bin/composer /usr/bin/composer

# PHP settings
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
RUN sed -i 's,^short_open_tag =.*$,short_open_tag = On,' "$PHP_INI_DIR/php.ini"
RUN sed -i 's,^memory_limit =.*$,memory_limit = ${PHP_MEMORY_LIMIT},' "$PHP_INI_DIR/php.ini"
RUN sed -i 's,^upload_max_filesize =.*$,upload_max_filesize = 20M,' "$PHP_INI_DIR/php.ini"
RUN sed -i 's,^post_max_size =.*$,post_max_size = 25M,' "$PHP_INI_DIR/php.ini"

# PHP-FPM settings
RUN sed -i 's,^;log_level =.*$,log_level = error,' "$PHP_INI_DIR/../php-fpm.conf"
RUN sed -i 's,^access.log =.*$,access.log = /dev/null,' "$PHP_INI_DIR/../php-fpm.d/docker.conf"

# Xdebug settings
RUN echo "xdebug.client_host=host.docker.internal" >> "$PHP_INI_DIR/conf.d/docker-php-ext-xdebug.ini"
RUN echo "xdebug.mode=develop,debug,trace,profile" >> "$PHP_INI_DIR/conf.d/docker-php-ext-xdebug.ini"
RUN echo "xdebug.start_with_request=trigger" >> "$PHP_INI_DIR/conf.d/docker-php-ext-xdebug.ini"
RUN echo "xdebug.profiler_output_name=cachegrind.%t" >> "$PHP_INI_DIR/conf.d/docker-php-ext-xdebug.ini"
RUN echo "xdebug.output_dir=/app/storage/xdebug" >> "$PHP_INI_DIR/conf.d/docker-php-ext-xdebug.ini"

# Get rid of php-fpm's pid logs
RUN echo -e "#!/bin/sh\nphp-fpm" > /usr/local/bin/start.sh && chmod +x /usr/local/bin/start.sh
CMD ["/usr/local/bin/start.sh"]

WORKDIR /app/fuelphp
