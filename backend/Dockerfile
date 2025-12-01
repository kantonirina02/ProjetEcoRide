FROM php:8.2-cli

WORKDIR /app

# Installer Composer
RUN apt-get update && apt-get install -y git unzip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copier les fichiers backend
COPY backend/ ./

# Installer les dépendances PHP (ignore ext-mongodb pour l'environnement sans extension)
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-req=ext-mongodb

EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
