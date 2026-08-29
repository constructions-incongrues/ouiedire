FROM php:7.4-cli
RUN pecl install pcov && docker-php-ext-enable pcov
WORKDIR /app/src
