FROM php:8.5-fpm

RUN ["rm","/bin/sh"]
RUN ["ln", "-s", "/bin/bash", "/bin/sh"]

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    git zip unzip libzip-dev libpq-dev libicu-dev \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && docker-php-ext-install -j$(nproc) bcmath \
    && docker-php-ext-install zip \
    && docker-php-ext-install gd \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-install pcntl \
    && docker-php-ext-install intl \
    && pecl install -o -f redis \
    && rm -rf /tmp/pear \
    &&  docker-php-ext-enable redis

ADD ./php.ini /usr/local/etc/php/php.ini
ADD ./xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

USER www-data
WORKDIR /var/www
