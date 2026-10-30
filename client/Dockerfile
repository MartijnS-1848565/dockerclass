# The Book Client (Laravel 13): a website that consumes the Book API.
# Same recipe as api/Dockerfile, but it needs no database driver:
# this app never touches MariaDB, it only talks HTTP to the API.
# It does need the soap extension, for PHP's built-in SoapClient.
FROM composer:2.8 AS composer

FROM php:8.5-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libxml2-dev \
    && docker-php-ext-install soap \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
RUN composer create-project laravel/laravel:^13.0 app --prefer-dist --no-interaction

# php-mqtt: to subscribe to and publish MQTT messages.
WORKDIR /var/www/app
RUN composer require php-mqtt/client:^2.1 --no-interaction

COPY src/ ./

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000
ENTRYPOINT ["entrypoint.sh"]
