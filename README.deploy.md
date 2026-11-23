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
4. Deploys to the AWS EC2 host through a repository self-hosted runner after a
   push to `main` or a version tag. Pull requests never run on the EC2 runner.

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

## 3. Automatic deployment on AWS

The `deploy` job uses a repository self-hosted Linux runner labelled
`klinikconnect`. Install that runner on the EC2 host from **Settings → Actions →
Runners → New self-hosted runner**, then configure it with the `klinikconnect`
label. Keep it dedicated to this repository and only run trusted deployment
jobs on it. GitHub warns against self-hosted runners on public repositories;
this workflow therefore keeps pull request builds on GitHub-hosted runners and
limits the EC2 runner to `main` pushes, version tags, and manual runs on those
refs.

On the EC2 host, keep `compose.prod.yaml` and the private `.env.production` in
`/opt/klinikconnect`. The deploy job uses its temporary `GITHUB_TOKEN` to log
into GHCR, pulls the images, and runs `docker compose up -d`. No EC2 private key
or long-lived GitHub token needs to be stored in repository secrets.

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


## External system integration

The server exposes `GET /api/v1/integrations/health`, `GET /api/v1/integrations/patients`, and `GET /api/v1/integrations/appointments`. Send `X-API-Key` containing `PATIENT_APPOINTMENT_INBOUND_API_KEY`. List endpoints support `page`, `per_page` (1�100), and `updated_since`. Appointments also support `date`.

`POST /api/v1/integrations/webhook` is an authenticated, encrypted event inbox. It acknowledges events without changing clinical, appointment, or payment records. Business mappings must be agreed with each provider before those updates are enabled.

Webhook JSON must contain `event_id`, `event_type`, `source`, and an object or array `data`. Send the inbound API key plus `X-Webhook-Timestamp` (Unix seconds) and `X-Webhook-Signature` (`sha256=` followed by the hexadecimal HMAC-SHA256 of `timestamp + "." + raw request body`, using `PATIENT_APPOINTMENT_WEBHOOK_VERIFY_SECRET`). Timestamps must be within five minutes. Repeating an identical event ID and body returns `duplicate`. Reusing an event ID with a different body returns 409.

KlinikConnect signs outgoing webhooks using `PATIENT_APPOINTMENT_WEBHOOK_SIGNING_SECRET` and sends `PATIENT_APPOINTMENT_OUTBOUND_API_KEY` as `X-API-Key`. The recipient must configure matching values. Set `INTEGRATION_OUTBOUND_WEBHOOK_URL` to the recipient's URL, then run `php artisan integrations:send-webhook /path/to/event.json`. Retry failures with the same event ID. Outbound delivery is an explicit command and does not automatically publish model changes.

The six `PATIENT_APPOINTMENT_*` variables describe KlinikConnect's side of the connection. `PATIENT_APPOINTMENT_WEBHOOK_URL` is this application's incoming URL. Each peer should receive its own credentials before independent production access is enabled.

Authenticated administrators can inspect `GET /api/v1/integrations/status` and read `GET /api/v1/integrations/billing/{resource}`, where `resource` is `customers`, `orders`, or `invoices`. The connector uses the supplied Billing contract's `/api/v1/sales/` paths. Use the peer credentials described below. The sales endpoints must authorize the selected credential on the Billing side.

The `HOSPITAL_EMR_*` settings belong to the Doctor and Medical Records system. Its REST endpoint contract and inbound API key remain pending. The Laboratory and Pharmacy REST endpoint contract also remains pending. URLs and keys alone do not enable record synchronization.

### Peer credentials and updated Billing contract

The provided `BILLING_ACCOUNTING_*`, `HOSPITAL_EMR_*`, and `LAB_PHARMACY_*` variables describe each peer's credentials from that peer's perspective. A peer's `OUTBOUND_API_KEY` is accepted by KlinikConnect's existing integration endpoints as `X-API-Key`. Incoming webhooks are verified with that peer's `WEBHOOK_SIGNING_SECRET`. Event IDs are isolated per authenticated peer, and the stored source is bound to the authenticated key.

For an outgoing webhook to a named peer, KlinikConnect sends the peer's `INBOUND_API_KEY` and signs with its `WEBHOOK_VERIFY_SECRET`. The timestamp and HMAC format are the same as the existing webhook contract above. Use `php artisan integrations:send-webhook /path/to/event.json --peer=billing`, `--peer=emr`, or `--peer=laboratory_pharmacy`. Delivery requires the recipient to expose the supplied URL and implement the same signing protocol. KlinikConnect's existing unqualified command remains available for the original default recipient.

The updated Billing base URL is `http://54.206.187.155/api/v1`. Its documented customer, order, and invoice endpoints remain under `/sales/`. The connector can send the supplied inbound key as `X-API-Key`, and it retains optional `BILLING_API_TOKEN` support for Billing's documented Sanctum authentication. Supplied keys are stored only in ignored or server environment files. `GET /api/v1/integrations/billing/connection` returns the actual authenticated invoice probe result without exposing invoice contents or credentials.

During the November 23 connection check, Billing health returned 200, the supplied key returned 401 on the documented sales API using both Bearer and `X-API-Key`, and POST to its supplied `/api/integration/webhooks` URL returned 404. The Doctor and Medical Records URL on port 3000 timed out. Those counterpart issues must be resolved before end-to-end exchange can be verified. The updated Billing document lists Sanctum and sales permissions for the sales endpoints and does not document the supplied integration webhook protocol.
