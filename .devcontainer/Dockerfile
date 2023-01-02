# Any *-apache image listed on this page : https://store.docker.com/images/php
FROM composer:2.2.12 as composer
FROM php:5.6.36

# Composer
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

# Default PHP configuration
RUN echo "date.timezone = Europe/Paris" > /usr/local/etc/php/php.ini

# Install additional packages and PHP extensions
RUN apt-get -y update \
    && apt-get -y install curl git zip \
    && apt-get -y clean \
    && apt-get -y autoremove

# Bower
RUN curl -fsSL https://deb.nodesource.com/setup_17.x | bash - && \
    apt-get install -y nodejs && \
    npm -g install bower

COPY ../src /workspaces/ouiedire/src

# Define default working directory
WORKDIR /workspaces/ouiedire/src

# Build application
RUN composer install && bower install
