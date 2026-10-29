FROM dunglas/frankenphp:1-php8.4 AS base

WORKDIR /app

# grpc builds from source (~15 min); a parallel build on every core froze Docker Desktop.
ARG IPE_PROCESSOR_COUNT=3
RUN IPE_PROCESSOR_COUNT=${IPE_PROCESSOR_COUNT} install-php-extensions grpc
RUN install-php-extensions bcmath intl opcache zip gd \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-app.ini"

ENV SERVER_NAME=":8080" \
    COMPOSER_ALLOW_SUPERUSER=1 \
    OPCACHE_VALIDATE=0

FROM base AS dev

ENV APP_ENV=dev

FROM base AS prod

# Worker mode boots the kernel once and keeps the Firestore connection and token between requests.
# Default is 2 workers per core seen, which can be the host's cores and overflow the 512Mi instance.
ENV APP_ENV=prod \
    APP_DEBUG=0 \
    FRANKENPHP_CONFIG="worker /app/public/index.php 4"

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev \
    && composer run-script --no-dev post-install-cmd \
    && php bin/console asset-map:compile \
    && chown -R www-data:www-data var
