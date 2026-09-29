#!/bin/sh
# Applies the code as it is: packages from composer.lock, YurbaCMF assets, migrations and caches
set -e
cd "$(dirname "$0")"

if [ -f composer.phar ]; then
    COMPOSER="php composer.phar"
else
    COMPOSER="composer"
fi

$COMPOSER install --no-dev --no-scripts --optimize-autoloader --no-interaction

# cached package lists can still name providers from before the install
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
php artisan package:discover --ansi

php artisan vendor:publish --tag=yurba-assets --force
php artisan migrate --force

php artisan optimize:clear
php artisan optimize
php artisan storage:link
