FROM php:8.3-fpm-alpine as base

RUN apk add --no-cache \
    $PHPIZE_DEPS \
    linux-headers \
    mpdecimal-dev \
    libzip-dev \
    zip \
    icu-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql bcmath zip gd sockets pcntl intl \
    && pecl install decimal \
    && pecl install redis \
    && docker-php-ext-enable decimal redis


FROM base as development

RUN cp $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini

# Install Xdebug
RUN pecl install xdebug && \
    docker-php-ext-enable xdebug

# Configure Xdebug
COPY deployment/dev/php/conf.d/xdebug.ini $PHP_INI_DIR/conf.d/docker-php-ext-xdebug.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /srv/app

FROM base as app

RUN cp $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini
COPY .. /srv/app
WORKDIR /srv/app
