# Documentation technique EcoRide

## Stack
- **Frontend** : HTML statique (pages/), SCSS compilé, Bootstrap 5, modules JS ES2022
- **Backend** : PHP 8.2, Symfony 7.1, Doctrine ORM (MySQL 8 par défaut), Twig pour mailers
- **Base relationnelle** : MySQL 8 (`DATABASE_URL=mysql://root:@127.0.0.1:3306/sf_ecoride?serverVersion=8.0&charset=utf8mb4`) ou PostgreSQL équivalent
- **Base NoSQL (optionnelle)** : MongoDB si vous activez le logger de recherches
- **Tests** : PHPUnit pour l’API, node --test pour les modules JS

## Organisation du repo
```
/
+- backend/              # projet Symfony
├+- config/
├+- migrations/
├+- src/
│  +- Controller/
│  +- Entity/
│  +- Document/
│  +- Service/
│  +- Repository/
├+- tests/
+- pages/, js/, scss/    # frontend statique
+- docs/                 # livrables (charte, doc, scripts)
```

## Principales routes (API)
| Methode | Endpoint | Description |
| --- | --- | --- |
| GET `/api/rides` | Recherche + filtres |
| POST `/api/rides/{id}/book` | Reservation (double confirmation) |
| POST `/api/rides/{id}/start` | Demarrage covoiturage |
| POST `/api/rides/{id}/finish` | Fin + demandes feedback |
| POST `/api/rides/{id}/feedback` | Retour passager (OK / incident) |
| GET `/api/moderation/issues` | Liste incidents (employes) |
| GET `/api/admin/metrics` | Series rides/bookings/signups + revenus |
| POST `/api/admin/users` | Creation compte employe |
| GET `/api/admin/search-logs` | Logs de recherche (si Mongo actif) |

## Donnees / schema (relationnel)
- Voir `docs/sql/bootstrap.sql` pour script DDL (MySQL/PostgreSQL).
- Indices clés : `idx_rides_from_to_start`, `idx_rp_status`, `idx_ledger_user_date`.
- Relations principales : `User` 1..N `Vehicle`; `Ride` 1..N `RideParticipant`; `Ride` 1..N `CreditLedger`.

## Securite
- Auth par session Symfony (`/api/auth/login`), cookies HTTPOnly.
- Autorisations : `ROLE_USER` (de base), `ROLE_DRIVER`, `ROLE_EMPLOYEE`, `ROLE_ADMIN`.
- Accès admin/employee vérifiés dans chaque controller.

## CI / Tests
- Tests back : `cd backend && php bin/phpunit`
- Tests front : `npm run test:js`
- Lint SCSS/JS : `npm run lint`

## Environnements
### Dev
```
cp backend/.env backend/.env.local
# configurer DB, mailer, (facultatif) Mongo
symfony server:start -d --port=8001
npm install
npx serve .
```

### Production
1. Construire conteneurs (ex : Docker Compose `php-fpm`, `caddy`, `postgres`, `mongo`).
2. Deployer assets statiques via CDN (pages + js + SCSS compile).
3. Executer `composer install --no-dev`, `php bin/console doctrine:migrations:migrate --no-interaction`.
4. Configurer variables : `APP_ENV=prod`, `APP_SECRET`, `DATABASE_URL`, (optionnel) `MONGODB_URL`, `MAILER_DSN`.
5. Redemarrer services, verifier `/api/health`.

## API externes
- Aucun appel externe obligatoire. Mailer peut utiliser SMTP (Mailtrap, SendGrid, etc.).

## Monitoring
- Monolog fichiers (`var/log/dev.log`/`prod.log`).
- SearchLogger pour audits de recherche.
- A brancher sur Sentry/ELK en production.
