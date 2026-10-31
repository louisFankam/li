# PharmaGest

Application de gestion de pharmacies (Laravel 12 + Vue 3 + Inertia + TypeScript + Tailwind), avec support hors ligne (PWA) et synchronisation.

## Prérequis

- PHP 8.2+ (recommandé 8.3)
- Composer 2
- Node.js 20+
- MySQL 8 (ou SQLite pour les tests)

## Installation

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

## Développement

```bash
php artisan serve
npm run dev
```

## Tests

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
npm run build
```

## Compte de démonstration

- Email : `admin@pharmagest.test`
- Mot de passe : `password`

## Documentation

- `docs/PLAN_IMPLEMENTATION.md` — feuille de route détaillée
- `docs/TODO.md` — suivi des tâches
