#!/bin/sh
set -e

# Migrations automatiques au démarrage du container
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Démarrer PHP-FPM
exec php-fpm
