# The Book API (Laravel 13).
#
# Multi-stage trick: the official composer image is only used as a place to
# copy the composer binary FROM. It does not end up in the final image.
FROM composer:2.8 AS composer

FROM php:8.5-cli-bookworm

# System packages + PHP extensions: pdo_mysql to talk to MariaDB, soap for the SOAP API.
# Everything in one RUN = one layer, and we clean the apt cache in that same layer.
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql zip soap \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer

# Generate a fresh Laravel 13 skeleton. This is the slow step (~1 min), but
# because nothing above it changes, Docker caches this layer and rebuilds are fast.
WORKDIR /var/www
RUN composer create-project laravel/laravel:^13.0 app --prefer-dist --no-interaction

# Extra packages: Lighthouse (GraphQL server), GraphiQL (a GraphQL explorer in the browser)
# and php-mqtt (to publish book events to the MQTT broker).
WORKDIR /var/www/app
RUN composer require nuwave/lighthouse:^6.0 mll-lab/laravel-graphiql:^4.1 php-mqtt/client:^2.1 --no-interaction

# Now overlay OUR code (routes, controller, model, migration...) on top of the skeleton.
# Only this layer and the ones after it are rebuilt when you change files in ./src.
COPY src/ ./

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000
ENTRYPOINT ["entrypoint.sh"]
