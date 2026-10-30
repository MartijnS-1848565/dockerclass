#!/bin/sh
# Runs every time the container starts.
# No "sleep 10" needed: compose only starts us once the db healthcheck passes.
set -e

php artisan migrate --force
php artisan db:seed --force

# exec replaces this shell with the PHP server, so it becomes PID 1 and
# receives the stop signal from `docker compose down` directly.
# --no-reload: without it, `artisan serve` hides most environment variables
# (DB_HOST, API_KEY, ...) from the web server and falls back to Laravel's .env file.
exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
