FROM php:8.2-fpm-alpine

RUN apk update && apk add mysql-client zip libzip-dev
RUN docker-php-ext-install pdo pdo_mysql zip

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer

RUN adduser -D app
USER app

WORKDIR /var/www/project
