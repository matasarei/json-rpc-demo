FROM php:8.0.5-fpm-alpine

RUN apk update && apk add mysql-client zip libzip-dev
RUN docker-php-ext-install pdo pdo_mysql zip

WORKDIR /var/www/project
