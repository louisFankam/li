# PharmaGest

Application de gestion de pharmacies (Laravel 12 + Vue 3 + Inertia + TypeScript + Tailwind), avec support hors ligne (PWA) et synchronisation.

## Prérequis

- PHP 8.4 (compatible 8.2+)
- Composer 2
- Node.js 22+
- MySQL 8 (les tests utilisent MySQL ; SQLite reste supporté pour les migrations)

## Installation

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

Créer les bases MySQL avant les migrations :

```sql
CREATE DATABASE pharmagest CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE pharmagest_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Adapter `DB_USERNAME` et `DB_PASSWORD` dans `.env`. L'utilisateur MySQL doit
pouvoir accéder aux deux bases. La suite de tests utilise `pharmagest_test`,
configurée dans `phpunit.xml`.

## Développement

```bash
php artisan serve
npm run dev
```

## Tests et qualité

```bash
composer test
composer lint
composer analyse
npm run lint
npm run format:check
npm run typecheck
npm test
npm run build
npm run pwa:check
```

La compatibilité des migrations SQLite peut être vérifiée avec :

```bash
touch /tmp/pharmagest.sqlite
DB_CONNECTION=sqlite DB_DATABASE=/tmp/pharmagest.sqlite php artisan migrate:fresh --force
DB_CONNECTION=sqlite DB_DATABASE=/tmp/pharmagest.sqlite php artisan migrate:rollback --force
DB_CONNECTION=sqlite DB_DATABASE=/tmp/pharmagest.sqlite php artisan migrate --force
```

## Compte de démonstration

- Email : `admin@pharmagest.test`
- Mot de passe : `password`

## Documentation

- `docs/PLAN_IMPLEMENTATION.md` — feuille de route détaillée
- `docs/TODO.md` — suivi des tâches
