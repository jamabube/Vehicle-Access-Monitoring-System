# Deploying VAMS to Render

This guide gets the **web application** (dashboard, RFID ingestion API,
queue worker, Reverb WebSocket server) live on Render. It does **not**
deploy the RFID hardware listener (`rfid:listen`) — that process dials a
physical UHF reader on your local network and must keep running on-site
(via `start-listener-auto.bat` / the Windows Scheduled Task), pointed at
your new public `RFID_LISTENER_API_URL`.

## Why Render, not Vercel

Vercel is a serverless/static platform: it has no persistent PHP process,
no MySQL, no long-running WebSocket server, and no background queue
worker — all of which this app requires (Reverb broadcast server, database
queue driver, `php artisan serve`). Render supports all of that, so this
guide targets Render only.

## What was added to the repo

| File | Purpose |
|---|---|
| `vams-webapp/Dockerfile` | Multi-stage build: Node stage compiles Vite/Tailwind assets, PHP 8.2 stage runs the app. Render has no native PHP runtime, so Docker is required. |
| `vams-webapp/docker-entrypoint.sh` | Caches config/routes/views and starts `php artisan serve` bound to Render's `$PORT`. |
| `vams-webapp/.dockerignore` | Keeps `vendor/`, `node_modules/`, `.env`, and logs out of the image build context. |
| `render.yaml` (repo root — *not* inside `vams-webapp/`) | Render Blueprint: defines the `vams-web` web service, `vams-queue` worker, `vams-reverb` WebSocket service, and a shared `vams-shared` env var group. |
| `.gitignore` | Now also excludes `*.mp4`/`*.m4a`/etc. so screen recordings don't bloat the repo. |

> `render.yaml` lives at the **workspace root**, alongside `vams-webapp/`,
> because Render Blueprints read `render.yaml` from the repo root by
> default. Since your Git repo root is this workspace folder, that's where
> it needs to be.

### One thing to decide first: repo root vs. subfolder

Your `git remote` (`https://github.com/jamabube/capstone.git`) tracks the
**whole workspace** (`agent-spec/`, `context/`, manuscripts, etc.), not
just `vams-webapp/`. Render Blueprints expect `render.yaml` at the Git
repo root, and `dockerfilePath`/`dockerContext` relative to that root — the
`render.yaml` created here already points into `vams-webapp/...` for this
reason (see Step 1).

If you'd rather keep your GitHub repo scoped to just the Laravel app
(recommended for a cleaner deploy and smaller repo — the manuscript/pptx
files don't belong in a production deploy), consider pushing `vams-webapp/`
to its own repo instead and moving `render.yaml` there. Ask if you want
help splitting it out.

## Step 1 — render.yaml is already wired for this layout

`render.yaml` (at the repo root) already points
`dockerfilePath: ./vams-webapp/Dockerfile` and `dockerContext: ./vams-webapp`
for all three services, matching this multi-project repo layout. Leave it
as-is unless you split `vams-webapp/` into its own standalone repo, in
which case change both to `./Dockerfile` and `.` and move `render.yaml`
into that new repo's root.

## Step 2 — Provision an external MySQL database

Render Postgres is free-tier eligible; Render's own MySQL offering is not.
Since this app uses `mysql`/`utf8mb4` migrations (see
`context/SCHEMA.md`), the fastest path is an external managed MySQL:

- **Railway** (free trial credits) — https://railway.app → New Project →
  Provision MySQL → copy `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`,
  `MYSQLUSER`, `MYSQLPASSWORD`.
- **Aiven** (free tier for MySQL) — https://aiven.io
- Any other MySQL 8-compatible host reachable from the public internet.

Keep the four credentials (host, database, username, password) handy for
Step 4.

## Step 3 — Push this repo to GitHub

```powershell
cd "C:\Users\jamabube\Downloads\Vehicle Access Monitoring System"
git add .
git commit -m "chore: add Docker + Render deployment config"
git push origin main
```

Nothing secret is committed: `.env` was never tracked, and `.gitignore`
excludes it at both the root and inside `vams-webapp/`.

## Step 4 — Create the Blueprint on Render

1. Go to https://dashboard.render.com → **New** → **Blueprint**.
2. Connect the `jamabube/capstone` GitHub repo, branch `main`.
3. Render detects `render.yaml` and shows the 3 services
   (`vams-web`, `vams-queue`, `vams-reverb`) plus the `vams-shared` group.
4. Click **Apply**. Render will prompt you to fill in every env var marked
   `sync: false` before the first deploy:
   - `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (from Step 2)
   - `APP_URL` — leave blank for now, come back after first deploy (Step 6)
   - `REVERB_HOST`, `VITE_REVERB_HOST`, `VITE_REVERB_APP_KEY` — same, come
     back after first deploy
   - Do the same for the `vams-shared` group's `DB_*` fields (they're
     separate values from the `vams-web` service's own `DB_*` — Render
     doesn't auto-share across services without `sync: false` prompts)

## Step 5 — First deploy

Render builds all three Docker images and deploys `vams-web` first (it has
the `preDeployCommand: php artisan migrate --force`, which creates your
schema on the external MySQL database on the very first deploy). Watch the
**Logs** tab for each service; a successful `vams-web` deploy ends with
Laravel's dev server bound to Render's `$PORT` and `/up` returning 200
(Render's health check).

## Step 6 — Wire up the real URLs (second deploy)

Once all three services are live, each has a `https://<name>.onrender.com`
URL. Go back to **Environment** for each service and fill in:

- `vams-web` → `APP_URL` = `https://vams-web.onrender.com` (or your custom
  domain)
- `vams-web` → `REVERB_HOST` = `vams-reverb.onrender.com` (hostname only,
  no `https://`)
- `vams-web` → `VITE_REVERB_HOST` = same as above
- `vams-web` → `VITE_REVERB_APP_KEY` = copy the auto-generated
  `REVERB_APP_KEY` value from the same service's Environment tab

Save — Render redeploys `vams-web` automatically. This second pass is
required because Vite bakes `VITE_*` values into the compiled JS at build
time, so `REVERB_APP_KEY` must exist before the asset build runs; the
Blueprint's `generateValue: true` creates it on the first deploy, but you
still need to manually copy it into `VITE_REVERB_APP_KEY` since Vite
env vars aren't auto-linked to Reverb's generated secret.

## Step 7 — Seed an admin user / RFID reader credentials

Use Render's **Shell** tab (or a one-off job) on `vams-web`:

```
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=RfidReaderSeeder
```

(Check `vams-webapp/database/seeders/` for the actual seeder names in
your codebase before running — list them with `php artisan db:seed --help`
if unsure, or read `context/TASKS.md` / `context/SCHEMA.md`.)

## Step 8 — Point the on-site RFID listener at the live API

On the Windows machine physically wired to the S4A UHF-202415 reader,
update its `vams-webapp/.env` (the **local** one, not Render's):

```
RFID_LISTENER_API_URL=https://vams-web.onrender.com/api/rfid/detections
```

Then restart `start-listener-auto.bat`. The reader stays on your LAN; only
its HMAC-signed HTTP POSTs cross the internet to the deployed app.

## Costs / free-tier reality check

- `vams-web` (web, `plan: free`) — Render's free web services **sleep
  after 15 minutes of inactivity** and cold-start on the next request
  (10–30s delay). Fine for a capstone demo, not for 24/7 production.
- `vams-queue` and `vams-reverb` are set to `plan: starter` (Render's
  cheapest paid tier) because: (a) background workers have no free tier
  at all, and (b) a free web service that sleeps would silently drop
  real-time dashboard pushes. To stay 100% free, delete the `vams-queue`
  service from `render.yaml` and instead trigger `php artisan queue:work
  --stop-when-empty` via a free Render **Cron Job**; you can similarly
  drop `vams-reverb` and accept the dashboard loses live push updates
  (verify no code path hard-requires a live WebSocket connection before
  doing this).
- The external MySQL provider (Railway/Aiven) has its own free-tier
  limits (Railway: trial credits expire; Aiven: free tier auto-pauses
  after a few weeks of inactivity).

## Troubleshooting

- **Deploy fails at `composer install`**: check the build log for a
  missing PHP extension — the Dockerfile installs `pdo_mysql mbstring
  bcmath gd zip intl opcache`; if a new package needs another extension,
  add it to the `docker-php-ext-install` line.
- **500 error after deploy**: run `php artisan config:clear` via Render's
  Shell tab, then re-check `APP_KEY` is set (it's `generateValue: true`
  so it should auto-populate on first deploy — confirm in Environment).
- **Dashboard doesn't update live**: confirm `REVERB_HOST`/
  `VITE_REVERB_HOST`/`VITE_REVERB_APP_KEY` were filled in per Step 6 and
  that `vams-reverb` isn't asleep (starter plan doesn't sleep, but double
  check the service is "Live" not "Suspended").
- **RFID detections never appear**: expected until the on-site listener
  (Step 8) is repointed at the deployed API and the physical reader is
  back online — see `context/RULES.md` for the HMAC handshake it must
  satisfy.
