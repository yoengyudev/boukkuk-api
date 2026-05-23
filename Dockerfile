FROM php:8.3-cli-alpine

RUN apk add --no-cache git icu-dev libpq-dev unzip \
    && docker-php-ext-install intl pdo_pgsql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . .

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && composer dump-autoload --optimize \
    && chmod +x render-start.sh

CMD ["./render-start.sh"]
