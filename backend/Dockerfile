FROM php:8.2-cli

WORKDIR /app

# Installer Composer
RUN apt-get update && apt-get install -y git unzip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Env par défaut pour le build (seront écrasées par Render)
ENV APP_ENV=prod \
    APP_SECRET=change-me \
    DATABASE_URL=sqlite:///var/data.db \
    MAILER_DSN=null://null \
    CORS_ALLOW_ORIGIN=*

# Copier les fichiers backend (le contexte de build doit être "backend")
COPY . ./

# Installer les dépendances PHP (ignore ext-mongodb, sans exécuter les scripts pendant le build)
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-req=ext-mongodb --no-scripts

COPY docker-entrypoint.sh /app/docker-entrypoint.sh
RUN chmod +x /app/docker-entrypoint.sh

EXPOSE 8000
CMD ["/app/docker-entrypoint.sh"]
