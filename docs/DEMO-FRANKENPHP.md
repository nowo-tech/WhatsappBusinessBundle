# Demo with FrankenPHP

**REQ-DEMO-001:** FrankenPHP demos must install **Nowo Twig Inspector** and **Nowo Hot Reload** together (`nowo-tech/twig-inspector-bundle` + `nowo-tech/hot-reload-bundle` in `require-dev`). Caddyfile: Mercure + `hot_reload` (and `worker { file …; watch }` in worker mode). Do not enable Hot Reload in production.

Demos under `demo/symfony8` run on **FrankenPHP** with Caddy.

## Table of contents

- [FRANKENPHP_MODE (REQ-DEMO-010)](#frankenphp_mode-req-demo-010)
- [Worker compatibility](#worker-compatibility)
- [Timeouts (REQ-RUNTIME-001)](#timeouts-req-runtime-001)
- [Run](#run)

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
