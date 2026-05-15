#!/bin/sh
set -e

# Générer les clés JWT si elles n'existent pas encore
if [ ! -f config/jwt/private.pem ]; then
    php bin/console lexik:jwt:generate-keypair --no-interaction
fi

# Vider le cache Symfony
php bin/console cache:clear --env=prod --no-debug

# Installer les assets des bundles (EasyAdmin, etc.)
php bin/console assets:install --env=prod --no-debug

# Exécuter les migrations en attente
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Démarrer Nginx + PHP-FPM via Supervisor
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
