# Guide de deploiement

## Prerequis
- Docker / Docker Compose (ou equivalent)
- PHP 8.2, Composer 2 si deploiement bare-metal
- Node.js ~ 18, npm ~ 9 (pour les assets statiques)
- MySQL 8 (par defaut dans `.env`)
- MongoDB 6 (optionnel, uniquement si vous activez les logs de recherche)
- SMTP (Mailtrap ou SendGrid)

## Variables d''environnement principales
```
APP_ENV=prod
APP_SECRET=<cle>
DATABASE_URL=mysql://app:password@db:3306/ecoride?serverVersion=8.0&charset=utf8mb4
MONGODB_URL=mongodb://mongo:27017/ecoride   # optionnel
MAILER_DSN=smtp://apikey:secret@smtp.sendgrid.net:587
```

## Etapes backend
1. cd backend
2. composer install --no-dev --optimize-autoloader
3. php bin/console doctrine:migrations:migrate --no-interaction
4. (optionnel) php bin/console doctrine:fixtures:load
5. php bin/console cache:clear --env=prod

## Etapes frontend
1. npm install
2. Compiler SCSS ou utiliser scss/main.css.
3. Deployer pages/, js/, scss compile sur CDN ou Nginx.
4. Definir window.__API_BASE_OVERRIDE si l''API est sur un autre domaine.

## Conteneurisation (extrait docker-compose)
```yaml
services:
  api:
    build: ./backend
    environment:
      APP_ENV: prod
      APP_SECRET: ${APP_SECRET}
      DATABASE_URL: ${DATABASE_URL}
      MONGODB_URL: ${MONGODB_URL}        # optionnel
      MAILER_DSN: ${MAILER_DSN}
    depends_on: [db, mongo]
  frontend:
    image: nginx:alpine
    volumes:
      - ./pages:/usr/share/nginx/html:ro
      - ./js:/usr/share/nginx/html/js:ro
    depends_on: [api]
  db:
    image: mysql:8
    environment:
      MYSQL_ROOT_PASSWORD: password
      MYSQL_DATABASE: ecoride
      MYSQL_USER: app
      MYSQL_PASSWORD: password
  mongo:
    image: mongo:6
```

## Verifications post-deploiement
- `/api/health` renvoie `{ "status": "ok" }`
- Creation compte admin / employe
- Parcours manuel QA : creer un trajet, reserver, feedback

## Sauvegardes
- MySQL : dumps quotidiens `mysqldump`
- MongoDB : `mongodump` hebdomadaire (si actif)

## Observabilite
- Rotation logs via `monolog.yaml`
- Integration possible avec Sentry, Grafana, etc.
