# KlinikConnect - SIA Individual

KlinikConnect is a clinic management system made up of a Laravel REST API and a
Vue 3 single-page application. Both projects live together in this repository.

## Repository structure

```
.
├── klinik-laravel-api/   # Laravel backend (REST API, authentication, database)
├── klinik-vuejs/         # Vue 3 + Vite frontend
├── compose.yaml          # Docker Compose stack for local development
├── compose.prod.yaml     # Production-style stack using the published images
├── .github/workflows/    # GitHub Actions image build/publish/deploy pipeline
├── README.docker.md      # Local Docker setup guide
├── README.deploy.md      # GitHub Actions and server deployment guide
└── README.md
```

## Tech stack

| Part     | Stack                                                                 |
| -------- | --------------------------------------------------------------------- |
| Backend  | PHP 8.4, Laravel, Laravel Sanctum, MySQL                               |
| Frontend | Vue 3, Vite, Pinia, Vue Router, Tailwind CSS                           |
| Tooling  | Docker Compose, Composer, npm                                          |

## Quick start with Docker

Requires Docker Desktop with Compose v2.

```sh
docker compose up --build
```

- Frontend: <http://localhost:5173>
- API: <http://localhost:8000>
- MySQL: published on port `3307`

Run the database migrations (and optionally the seeders) on first setup:

```sh
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

See [README.docker.md](README.docker.md) for details.

### Demo accounts

`php artisan db:seed` (or `RUN_SEEDER=true` in the production stack) creates
these accounts with the password `islamislam`:

| Role | Email |
| --- | --- |
| Admin | `admin@gmail.com` |
| Doctor | `doctor@gmail.com` |
| Nurse | `nurse@gmail.com` |
| Pharmacist | `pharmacist@gmail.com` |
| Laboratorist | `laboratorist@gmail.com` |
| Patient | `patient@gmail.com` |

## Production deployment

The production stack builds one Nginx container for the Vue app and one
Laravel/Apache container, backed by MySQL. Images are published to GitHub
Container Registry by GitHub Actions, and the workflow can deploy them to a
server over SSH when the deploy secrets are configured.

See [README.deploy.md](README.deploy.md) for the full walkthrough.

## Manual setup

### Backend

```sh
cd klinik-laravel-api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### Frontend

```sh
cd klinik-vuejs
npm install
npm run dev
```

The Vite dev server proxies API, Sanctum, storage, and SSE requests to the
Laravel API, so requests share the same `localhost` origin.
