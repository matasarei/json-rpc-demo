FROM dunglas/frankenphp:1-php8.4

RUN install-php-extensions intl opcache zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Plain HTTP on port 80: the demo is reached on localhost, no certificate needed.
ENV SERVER_NAME=:80 \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-interaction --no-progress --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize

COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
ENTRYPOINT ["app-entrypoint"]
CMD ["--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
