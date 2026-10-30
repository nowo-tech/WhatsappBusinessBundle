# Security

## Table of contents

- [Reporting a vulnerability](#reporting-a-vulnerability)
- [Threat model (this bundle)](#threat-model-this-bundle)
- [Logging (REQ-OBS-001)](#logging-req-obs-001)
- [Supported versions](#supported-versions)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)
- [AI security audit (REQ-SEC-004)](#ai-security-audit-req-sec-004)

## Reporting a vulnerability

Please report security issues privately via GitHub Security Advisories on [`nowo-tech/WhatsappBusinessBundle`](https://github.com/nowo-tech/WhatsappBusinessBundle) or email the maintainers listed in `composer.json`. Do not open a public issue for undisclosed vulnerabilities.

Also see [`.github/SECURITY.md`](../.github/SECURITY.md).

## Threat model (this bundle)

| Risk | Mitigation |
|------|------------|
| Stolen Cloud API access token | Tokens only via env/`%env()%`; never logged; never committed; redacted in dumps/profiler (`__debugInfo`, VarDumper casters, `SensitiveParameter`) |
| Forged webhooks | HMAC `X-Hub-Signature-256` with `app_secret`; `allow_unsigned_webhooks` defaults false (including the zero-config mock profile) |
| SSRF via Graph base URI | Config allows `https://` URLs only |
| Graph path injection via media id | `downloadMedia()` (Cloud API transport) only accepts ids matching `^[0-9A-Za-z_-]+$` (no `/`, `?`, `..`, `%`, whitespace or trailing newline); otherwise `InvalidMediaIdException` before any Graph call (since 1.2.0) |
| SSRF / token theft via media URL | Downloads only to Meta hosts or the configured `graph_base_uri` host; `max_redirects: 0`; no `userinfo` in URL |
| Hung outbound HTTP under FrankenPHP worker | Profile `timeout` + `connect_timeout` on every Graph call; PHP / Caddy write / `max_wait_time` layered above it (REQ-RUNTIME-001) |
| Oversized webhook bodies (memory / CPU) | Host limits request bodies at the web server (demo: Caddy `request_body { max_size 1MB }` caps what PHP reads; a truncated signed body fails HMAC); HMAC is checked before JSON parsing |
| Unsigned webhooks left on in production | Container compile fails in `prod` when `allow_unsigned_webhooks: true` and `app_secret` is empty |
| PII in logs | Phone numbers masked; message bodies and tokens never logged or dumped |
| Mock transport leaking across worker requests | `ResetInterface` / `kernel.reset` + per-request clear + bounded `recorded()` (see [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md)) |

## Logging (REQ-OBS-001)

- **info** — send success: profile, masked `to`, message type, Graph message id
- **error** — send/transport failure: profile, HTTP status, Graph error code (not the token)
- **warning** — webhook signature rejection / unknown profile
- **info** — webhook accepted: counts only

## Supported versions

Security fixes are provided for the latest minor of the current major release line. See [`.github/SECURITY.md`](../.github/SECURITY.md#supported-versions).

## Release security checklist (12.4.1)

Before every release the maintainer confirms each row (a note in the release PR or tag message is enough; REQ-SEC-002).

| # | Item | How this bundle meets it | Check before tagging |
|---|------|--------------------------|----------------------|
| 1 | `docs/SECURITY.md` + `.github/SECURITY.md` | This document (threat model, logging, checklist) and the GitHub policy file | Both files current; threat model updated for new features |
| 2 | `.env` in `.gitignore` | Root `.gitignore` ignores `.env`, `.env.local`, `.env.*.local`; the demo also ignores `.env.dev` | `git ls-files '*.env*'` lists only `.env.example` / `.env.test` |
| 3 | No secrets in the repository | Tokens and app secrets only through `%env()%`; tests use obvious placeholders (`REDACTED-TEST`, `demo-token`) | `git grep -nE 'EAA[A-Za-z0-9]{20,}'` is empty; GitGuardian clean |
| 4 | Safe `config/` recipe | `.symfony/recipe/.../nowo_whatsapp_business.yaml` reads every credential from env vars and ships `allow_unsigned_webhooks: false` | Recipe has no literal token / secret / verify token |
| 5 | Input / output (validation + escaping) | Webhook POST: HMAC `X-Hub-Signature-256` checked with `hash_equals` before parsing; GET challenge: verify token compared with `hash_equals`; parser only reads typed scalars; phone numbers normalised to 8–15 digits; media ids `^[0-9A-Za-z_-]+$`; `graph_base_uri` `https://` only; media downloads allow-listed (Meta hosts / Graph host, no userinfo, no redirects). No HTML output (plain-text responses) | Tests in `tests/Unit/Webhook`, `tests/Unit/Http`, `tests/Unit/Message` green |
| 6 | Dependencies (`composer audit`) | CI job "Composer audit (Critical/High)" (`composer audit --locked`); Dependabot for Composer and GitHub Actions | `composer audit --locked` clean on the release commit (run locally while Actions are unavailable, see [GITHUB_CI.md](GITHUB_CI.md#ci-evidence-for-this-private-repository-req-ci-003)) |
| 7 | No secrets in logs | Logs carry profile, masked phone (`*******1111`), message type, Graph ids and HTTP / Graph codes only; `SecretRedactor` + VarDumper casters + `#[SensitiveParameter]` keep tokens out of dumps, exceptions and the profiler ([Logging](#logging-req-obs-001)) | New log calls reviewed for tokens, bodies and full phone numbers |
| 8 | Cryptography | Only HMAC-SHA256 (`hash_hmac`) for webhook signatures with constant-time `hash_equals`; no custom crypto, no secret storage | No new crypto primitives |
| 9 | Permissions / exposure | Single public HTTP surface: `/whatsapp/webhook[/{profile}]`, authenticated by HMAC (machine endpoint, no session / CSRF). Unknown profiles → 404; bad token / signature → 403. `allow_unsigned_webhooks: true` without `app_secret` is refused at container compile time in `prod`. All bundle services are private | Route list unchanged (`debug:router`); no new public services |
| 10 | Limits / DoS | Graph calls: profile `timeout` + `max_duration` + cURL connect timeout, `max_redirects: 0` (REQ-RUNTIME-001); mock transport records at most 100 entries; host body-size limit for the webhook (demo: Caddy `request_body { max_size 1MB }`) and FrankenPHP `max_wait_time` | Timeout hierarchy still valid ([CONFIGURATION.md](CONFIGURATION.md#timeouts-req-runtime-001)) |
| 11 | AI security audit (REQ-SEC-004) | Latest recorded grade below ([AI security audit](#ai-security-audit-req-sec-004)) and in `BUNDLES_SECURITY_ANALYSIS.md` | Grade **Pass**; re-audit when the webhook, HTTP or DI surface changes |

## AI security audit (REQ-SEC-004)

| Field | Value |
| ----- | ----- |
| Risk level | **Low** |
| AI audit date | 2026-09-14 |
| Method | Cursor agent static review of `src/`, DI config, webhook controller, media download policy, dump redaction, transport, `docs/SECURITY.md`, `.github/SECURITY.md` |
| Grade | **Pass (good)** |
| Open residuals | Host must protect webhook routes and store tokens only in env/`%env()%`; no Critical/High findings. |
