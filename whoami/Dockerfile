# The "whoami" page: one PHP file served by PHP's built-in web server.
FROM php:8.5-cli-alpine

WORKDIR /app
COPY index.php .

# Don't run as root: a good habit everywhere, and many Kubernetes clusters require it.
USER www-data

EXPOSE 8080
# Passing index.php as a "router script" sends every URL to it.
CMD ["php", "-S", "0.0.0.0:8080", "index.php"]
