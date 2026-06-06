FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    postgresql-dev \
    libpng-dev \
    libzip-dev \
    icu-dev \
    rabbitmq-c-dev \
    $PHPIZE_DEPS \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        intl \
        opcache \
        gd \
        zip \
    && apk del --purge $PHPIZE_DEPS

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html

RUN chown -R www-data:www-data /var/www/html

USER www-data
