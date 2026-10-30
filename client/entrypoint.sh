#!/bin/sh
set -e

# --no-reload: without it, `artisan serve` hides most environment variables
# (DB_HOST, API_KEY, ...) from the web server and falls back to Laravel's .env file.
exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
