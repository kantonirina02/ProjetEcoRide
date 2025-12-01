# EcoRide
Plateforme de covoiturage interne permettant aux collaborateurs de proposer, reserver et gerer des trajets en toute autonomie.

## Architecture
- **Frontend statique** (`pages/`, `js/`) : rendu cote navigateur, consommation de l'API et gestion des interactions (reservations, vehicules, preferences).
- **Backend Symfony** (`backend/`) : API REST (`/api/...`), auth session, persistance Doctrine, regles metier (validation trajets, credits, notifications).
- **Styles** : Bootstrap 5 + Bootstrap Icons (importes depuis `node_modules`).

## Prerequis
- Node.js 18 et npm 9
- PHP 8.2
- Composer
- Base relationnelle (MySQL 8 par defaut, ajustable) + MongoDB si vous activez les logs de recherche

## Installation & demarrage rapide
1. **Frontend**
   ```bash
   npm install
   ```
2. **Backend**
   ```bash
   cd backend
   composer install
   ```
3. **Config** : copier `.env` en `.env.local` et ajuster `DATABASE_URL` (ex. SQLite : `DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"`).
4. **Base**
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   php bin/console doctrine:fixtures:load --no-interaction   # optionnel
   ```
5. **Lancer l'API**
   ```bash
   symfony server:start --port=8001
   # ou php -S 127.0.0.1:8001 -t public
   ```
6. **Servir le front** : `npx serve .` puis ouvrir `http://127.0.0.1:3000`.
   Les scripts front autodetectent l'API (`http://127.0.0.1:8001/api` par defaut) ; sinon utiliser `<meta name="api-base">` ou `window.__API_BASE_OVERRIDE`.

## Fonctionnalites principales
- Authentification par session, profil (photo, preferences conducteur/passager).
- Parc vehicules conducteur : marque, modele, energie, couleur, plaque (unicite), date de premiere immatriculation obligatoire.
- Creation trajets avec commission plateforme (2 credits), controle prix/dispos/dates.
- Reservation en double confirmation, annulation, credits mis a jour.
- Workflow conducteur : demarrer / terminer / feedback passagers, remboursements, incidents.
- Espaces employe (moderation avis, trajets signales) et admin (comptes, suspensions, metriques, logs recherche, creation employes).

## Scripts utiles
- `npm run test:js`
- `php bin/phpunit` (dans `backend/`)
- `php bin/console lint:twig|yaml|container`

## Comptes initiaux (prod)
- Créer un admin : `cd backend && php bin/console app:user:create admin@ecoride.fr AdminRoot --admin --credits=200`
- Créer un employé : `cd backend && php bin/console app:user:create employe@ecoride.fr ModEco --employee --credits=100`
- Les mots de passe doivent rester forts (8+ caractères, min/maj/chiffre/spécial). Relancer la commande si l’email existe déjà.

## Deploiement
- Vars prod : `APP_ENV=prod`, `APP_SECRET`, `DATABASE_URL`, `MONGODB_URL`, `MAILER_DSN`.
- `composer dump-env prod` puis `php bin/console cache:clear --env=prod`.
- Servir les pages statiques (index.html, pages/, js/, scss compile).

## Liens a fournir
- Depot public GitHub
- URL deploiement applicatif
- URL Kanban (Trello / Notion / GitHub Projects)
