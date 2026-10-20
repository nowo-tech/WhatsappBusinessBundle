# Demo with FrankenPHP

**REQ-DEMO-001:** FrankenPHP demos must install **Nowo Twig Inspector** and **Nowo Hot Reload** together (`nowo-tech/twig-inspector-bundle` + `nowo-tech/hot-reload-bundle` in `require-dev`). Caddyfile: Mercure + `hot_reload` (and `worker { file …; watch }` in worker mode). Do not enable Hot Reload in production.

Demos under `demo/symfony8` run on **FrankenPHP** with Caddy.

## Table of contents

- [FRANKENPHP_MODE (REQ-DEMO-010)](#frankenphp_mode-req-demo-010)
- [Worker compatibility](#worker-compatibility)
- [Timeouts (REQ-RUNTIME-001)](#timeouts-req-runtime-001)
- [Run](#run)
- [Start the demo in development](#start-the-demo-in-development)
- [Start the demo in production mode](#start-the-demo-in-production-mode)
- [Troubleshooting](#troubleshooting)

## FRANKENPHP_MODE (REQ-DEMO-010)

In `.env.example` / Compose:

```dotenv
FRANKENPHP_MODE=worker
```

| Value | Behaviour |
|-------|-----------|
| `worker` (default) | `php_server { worker ... }` — app stays in memory; mock transport clears per request (even without `kernel.reset`) |
| `classic` | Classic `php_server` without worker |

Switch mode by changing `.env` and recreating containers (`docker compose up -d`); no image rebuild required.

See `docker/entrypoint.sh` and `docker/frankenphp/Caddyfile` / `Caddyfile.dev`.

## Worker compatibility

- `MockTransport` implements `ResetInterface` and is tagged `kernel.reset`; the bundle also clears it at the start of every main request and keeps at most `MockTransport::DEFAULT_MAX_RECORDED` (100) entries, so recorded sends never leak between requests even without the services resetter.
- `WhatsappClient` / `GraphApiClient` / `CloudApiProfile` are readonly and store no per-request Graph bodies.
- Webhook controller returns 200 after dispatching events (host listeners must stay fast or queue work).

## Timeouts (REQ-RUNTIME-001)

Demo profile defaults: `timeout: 10`, `connect_timeout: 5`. Keep PHP `max_execution_time` and Caddy write timeouts **greater** than the profile timeout. Documented in [CONFIGURATION.md](CONFIGURATION.md).

## Run

From the bundle root:

```bash
make -C demo up-symfony8
```

Open the URL printed by `make up` (`Demo started at: http://localhost:<PORT>`).

## Start the demo in development

```bash
cd demo/symfony8
cp -n .env.example .env        # first run only; set FRANKENPHP_MODE=classic for request mode + hot reload
make up                        # docker compose up -d (entrypoint selects Caddyfile / Caddyfile.dev from FRANKENPHP_MODE)
```

Open http://localhost:8031. In development `APP_ENV=dev`; `Caddyfile.dev` (classic mode) serves PHP without workers, and `docker/php-dev.ini` (when present) sets `opcache.revalidate_freq=0` so code changes are picked up immediately.

## Start the demo in production mode

The production pattern is the worker Caddyfile (`php_server { worker { file /app/public/index.php; watch } }`), which is the default (`FRANKENPHP_MODE=worker`).

```bash
cd demo/symfony8
# .env: FRANKENPHP_MODE=worker, APP_ENV=prod, APP_DEBUG=0, a real APP_SECRET
make down && make up           # recreate containers so the new env is applied
docker compose exec php php bin/console cache:clear --env=prod
```

In a real deployment, bake the application into the image (no bind mount), keep `FRANKENPHP_MODE=worker`, and warm the cache at build time. Worker-mode state rules are documented in the bundle worker audit (`docs/FRANKENPHP-WORKER-AUDIT.md`, when present).

## Troubleshooting

| Symptom | Cause | Fix |
| --- | --- | --- |
| Changes in PHP code are not visible | Worker mode keeps the kernel in memory | Use `FRANKENPHP_MODE=classic` in development, or restart the container (`make down && make up`) |
| `FRANKENPHP_MODE` change has no effect | `docker compose restart` does not reload `.env` | Recreate the containers: `make down && make up` (or `docker compose up -d`) |
| Port already in use on `make up` | Another stack uses the demo port | Change `PORT` in `.env` and recreate the containers |
| Bundle changes are not picked up | Composer path repository / autoload cache out of date | Run `make update-bundle` (or `composer dump-autoload` + `cache:clear` in the container) |
| HTTP 502 / container restarts | PHP fatal error at boot (missing `vendor/`, bad config) | Check `docker compose logs php`; run `make install` and `bin/console cache:clear` |
| Stale Twig output in development | Twig cache enabled | Ensure `config/packages/dev/twig.yaml` sets `twig.cache: false` (when the demo ships it) and clear the cache |
