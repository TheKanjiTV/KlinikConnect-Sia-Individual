# KlinikConnect Docker setup

Requirements: Docker Desktop (with Compose v2).

From this directory, start the Vue frontend, Laravel API, and MySQL database:

```sh
docker compose up --build
```

Open the frontend at <http://localhost:5173>. The API is available at
<http://localhost:8000> and MySQL is published on port `3307` for local tools.
Vue proxies API, Sanctum, storage, and SSE requests through the Vite dev server,
so browser requests and Sanctum cookies use the same localhost origin.

On first startup, Composer dependencies are installed, Laravel's local app key
is generated if it is missing, and the public storage link is created. Apply
database migrations in another terminal:

```sh
docker compose exec app php artisan migrate
```

If the application needs its seed data:

```sh
docker compose exec app php artisan db:seed
```

To stop the services, press `Ctrl+C` and run `docker compose down`. Database,
uploaded files, and installed dependencies persist in named Docker volumes.
To delete the database and uploads as well, run `docker compose down -v`.

Set `DB_PASSWORD` and `DB_ROOT_PASSWORD` in a root `.env` file before using this
setup beyond local development. The defaults are intended only for local use.
