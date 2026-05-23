#!/usr/bin/env sh
set -e

php artisan config:clear
php artisan migrate --force
php artisan config:cache

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
