#!/bin/sh
set -e

# Render injects PORT; default to 8000 for local docker run
PORT="${PORT:-8000}"

# Cache config/routes/views for production performance (safe: env vars are
# already present as real container env vars, not just in .env)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Ensure the public storage symlink exists (uploaded files, if any)
php artisan storage:link --force || true

echo "Starting web server on 0.0.0.0:${PORT}"
exec php artisan serve --host=0.0.0.0 --port="${PORT}"
