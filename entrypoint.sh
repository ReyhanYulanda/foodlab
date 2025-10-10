#!/bin/bash
set -e

echo "🔧 Running Laravel startup checks..."

if [ ! -f /var/www/html/bootstrap/cache/config.php ]; then
  echo "⚙️ Rebuilding Laravel cache..."
  php artisan config:clear
  php artisan cache:clear
  php artisan route:clear
  php artisan view:clear
  php artisan config:cache
else
  echo "✅ Laravel cache exists, skipping rebuild."
fi

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "🚀 Starting PHP-FPM..."
exec php-fpm