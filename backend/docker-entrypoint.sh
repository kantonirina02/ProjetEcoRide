#!/bin/sh
set -e

# Migrations (SQLite)
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

# Comptes initiaux (ignorer si déjà créés)
php bin/console app:user:create admin@ecoride.fr AdminRoot --admin --credits=20 --password "Admin#2025!" || true
php bin/console app:user:create employe@ecoride.fr ModEco --employee --credits=20 --password "Employe#2025!" || true

# Lancer le serveur PHP
exec php -S 0.0.0.0:8000 -t public public/index.php
