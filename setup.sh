#/bin/sh

chmod 777 -R bootstrap/cache
chmod 777 -R storage
cp .env.example .env
php artisan key:generate
