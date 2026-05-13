#!/bin/sh
set -e

# Vider le cache Symfony
php bin/console cache:clear --env=prod --no-debug

# Exécuter les migrations en attente
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Démarrer Nginx + PHP-FPM via Supervisor
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
