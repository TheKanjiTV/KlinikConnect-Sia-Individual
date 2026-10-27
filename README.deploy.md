# Deploying KlinikConnect

The production stack runs three containers:

| Container | Purpose |
| --- | --- |
| `web` | Nginx serving the built Vue app and proxying `/api`, `/sanctum`, and `/storage` to the API |
| `api` | Laravel + Apache. Runs migrations on startup and optionally seeds demo data |
| `db` | MySQL 8 |

Only the `web` container is published (port `8080` by default). Because the API
is proxied through the same origin as the frontend, Sanctum session cookies work
without CORS problems.

## How the GitHub Actions pipeline works

The workflow at `.github/workflows/docker-publish.yml`:

1. Runs on every push to `main`, every pull request to `main`, and manual runs.
2. Builds both images with Buildx.
3. Pushes the images to GitHub Container Registry (`ghcr.io`) on non-PR runs:
   - `ghcr.io/thekanjitiv/klinikconnect-api`
   - `ghcr.io/thekanjitiv/klinikconnect-web`
4. Optionally deploys over SSH when the server secrets below are configured.

Pull requests only build the images; they are never pushed.

## 1. Push the code to GitHub

If you have not authenticated the GitHub CLI yet:

```sh
gh auth login
git add .
git commit -m "Add Docker deployment"
git push origin main
```

After the push, open the **Actions** tab in the repository. Both images appear
under **Packages** on the right side of the repository page.

By default, GHCR packages are private. Either:

- make each package public in **Package settings → Change visibility**, or
- run `docker login ghcr.io` on the server with a personal access token that has
  `read:packages`.

## 2. Prepare the server

You need a Linux server with Docker Engine and Docker Compose v2 installed
(a small VPS is enough). Open ports `80` and `443` in the firewall.

```sh
sudo mkdir -p /opt/klinikconnect
cd /opt/klinikconnect
```

Copy `compose.prod.yaml` and `.env.production.example` to the server, then:

```sh
cp .env.production.example .env.production
nano .env.production
```

Set at least:

- `APP_KEY` — generate a value with:
  `openssl rand -base64 32` (prefix it with `base64:`)
- `APP_URL` and `FRONTEND_URL` — your public HTTPS URL
- `SANCTUM_STATEFUL_DOMAINS` — the host name only, e.g.
  `klinik.example.com`. Include the port when it is not the default,
  e.g. `localhost:8080`.
- `CORS_ALLOWED_ORIGINS` — the full public URL
- `DB_PASSWORD` and `DB_ROOT_PASSWORD`
- `RUN_SEEDER=false` by default. Enable it only for an isolated demo environment,
  because the demo accounts use a shared, known password.

Keep `SESSION_SECURE_COOKIE=true` when the public URL uses HTTPS. If you only
want to test over plain HTTP (for example `http://SERVER_IP:8080`), set
`SESSION_SECURE_COOKIE=false` and use the matching `APP_URL`/`FRONTEND_URL` and
`CORS_ALLOWED_ORIGINS`; browsers will not send secure cookies over HTTP.

Then pull and start the published images:

```sh
docker login ghcr.io -u TheKanjiTV
docker compose --env-file .env.production -f compose.prod.yaml pull
docker compose --env-file .env.production -f compose.prod.yaml up -d
```

Check the logs:

```sh
docker compose --env-file .env.production -f compose.prod.yaml logs -f api
```

Open `https://your-domain` if you put a TLS proxy in front of the stack. For a
plain HTTP test, open `http://SERVER_IP:8080` and make sure
`SESSION_SECURE_COOKIE=false`. For HTTPS, put Caddy, Nginx, or Traefik in front
of port `8080` and point your domain at the server.

### Local production build (optional)

You can also build and run the production stack locally without GHCR:

```sh
cp .env.production.example .env.production
# set APP_KEY, DB passwords, APP_URL=http://localhost:8080,
# FRONTEND_URL=http://localhost:8080,
# SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8080,127.0.0.1,127.0.0.1:8080,
# CORS_ALLOWED_ORIGINS=http://localhost:8080,
# SESSION_SECURE_COOKIE=false
docker compose --env-file .env.production -f compose.prod.yaml up --build
```

The app is then available at <http://localhost:8080>.

## 3. Optional: automatic deployment over SSH

Add these repository secrets (**Settings → Secrets and variables → Actions**):

| Secret | Required | Description |
| --- | --- | --- |
| `DEPLOY_HOST` | yes | Server IP or hostname |
| `DEPLOY_USER` | yes | SSH user, e.g. `ubuntu` |
| `DEPLOY_SSH_KEY` | yes | Private SSH key for that user |
| `DEPLOY_PORT` | no | SSH port, defaults to `22` |
| `DEPLOY_PATH` | no | Project folder on the server, defaults to `/opt/klinikconnect` |

The deploy job logs in to GHCR with the workflow token, runs `docker compose
pull`, and recreates the containers. It is skipped automatically when the
secrets are missing, so the build pipeline still succeeds.

## Demo accounts

`RUN_SEEDER=true` creates these accounts with password `islamislam`. Keep seeding
disabled on a public deployment:

| Role | Email |
| --- | --- |
| Admin | `admin@gmail.com` |
| Doctor | `doctor@gmail.com` |
| Nurse | `nurse@gmail.com` |
| Pharmacist | `pharmacist@gmail.com` |
| Laboratorist | `laboratorist@gmail.com` |
| Patient | `patient@gmail.com` |

For a real deployment, set `RUN_SEEDER=false` and change or remove the demo
users before exposing the site publicly.
