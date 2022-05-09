# Any *-apache image listed on this page : https://store.docker.com/images/php
FROM composer:2.2.12 as composer
FROM php:5.6.36-apache
e
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

ENV APACHE_DOCUMENT_ROOT /usr/local/src/app/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Enable additional Apache modules
RUN a2enmod rewrite

# Copy application sources to container
COPY --chown=www-data:www-data ./src /usr/local/src/app

# Define default working directory
WORKDIR /usr/local/src/app

# Build application
RUN composer install && bower install
