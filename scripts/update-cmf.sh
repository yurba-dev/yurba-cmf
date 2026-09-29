#!/bin/sh
# Updates YurbaCMF to the newest release composer.json allows, then applies it with deploy.sh
set -e
cd "$(dirname "$0")"

if [ -f composer.phar ]; then
    COMPOSER="php composer.phar"
else
    COMPOSER="composer"
fi

$COMPOSER update yurba/cmf --with-dependencies --no-dev --no-scripts --optimize-autoloader --no-interaction

# the local copy the site used before YurbaCMF came from Packagist
if [ -d packages/yurba-cmf ]; then
    rm -rf packages/yurba-cmf
    rmdir packages 2>/dev/null || true
fi

sh ./deploy.sh
