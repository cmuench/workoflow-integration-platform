#!/bin/bash
set -eo pipefail

echo ""
echo "==> Installing Composer dependencies..."
composer install --no-interaction --prefer-dist

echo ""
echo "==> Updating database schema..."
php bin/console doctrine:schema:update --force

echo ""
echo "==> Setting up MinIO buckets..."
php bin/console app:create-bucket

echo ""
echo "==> Clearing application cache..."
php bin/console cache:clear

echo ""
echo "✓ Setup complete."
