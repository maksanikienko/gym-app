#!/usr/bin/env bash
# Run on the server from the repo root: ./deploy/deploy.sh
set -euo pipefail
cd "$(dirname "$0")/.."

( cd backend
  composer install --no-dev --optimize-autoloader --no-interaction
  php artisan migrate --force
  php artisan db:seed --force          # idempotent: catalogue + public template
  php artisan config:cache
  php artisan route:cache
)

( cd frontend
  npm ci
  npm run build
)

sudo systemctl reload php8.4-fpm
echo "Deployed."
