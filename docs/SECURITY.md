# Security

## Table of contents

- [Reporting a vulnerability](#reporting-a-vulnerability)
- [Threat model (this bundle)](#threat-model-this-bundle)
- [Logging (REQ-OBS-001)](#logging-req-obs-001)
- [Supported versions](#supported-versions)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)
- [AI security audit (REQ-SEC-004)](#ai-security-audit-req-sec-004)
- [Residual risks](#residual-risks)

## Reporting a vulnerability

Please report security issues privately via GitHub Security Advisories on [`nowo-tech/WhatsappBusinessBundle`](https://github.com/nowo-tech/WhatsappBusinessBundle) or email the maintainers listed in `composer.json`. Do not open a public issue for undisclosed vulnerabilities.

Also see [`.github/SECURITY.md`](../.github/SECURITY.md).

## Threat model (this bundle)

| Risk | Mitigation |
|------|------------|
| Stolen Cloud API access token | Tokens only via env/`%env()%`; never logged; never committed; bundle objects are redacted in dumps (`__debugInfo`, VarDumper casters, `SensitiveParameter`); the `Authorization` header is built in a local variable, so it never appears in exception trace arguments (`FlattenException`, error trackers; fixed in 1.3.0, audit BU L1). **Residual (dev only):** the Symfony profiler HttpClient panel records request headers / curl command, including `Authorization: Bearer` — see [Residual risks](#residual-risks) |
| Forged webhooks | HMAC `X-Hub-Signature-256` with `app_secret`; `allow_unsigned_webhooks` defaults false (including the zero-config mock profile) |
| SSRF via Graph base URI | Config allows `https://` URLs only |
| Graph path injection via media id | `downloadMedia()` (Cloud API transport) only accepts ids matching `^[0-9A-Za-z_-]+$` (no `/`, `?`, `..`, `%`, whitespace or trailing newline); otherwise `InvalidMediaIdException` before any Graph call (since 1.2.0) |
| SSRF / token theft via media URL | Downloads only to Meta hosts or the configured `graph_base_uri` host; `max_redirects: 0`; no `userinfo` in URL |
| Hung outbound HTTP under FrankenPHP worker | Profile `timeout` + `connect_timeout` on every Graph call; PHP / Caddy write / `max_wait_time` layered above it (REQ-RUNTIME-001) |
| Oversized webhook bodies (memory / CPU) | Host limits request bodies at the web server (demo: Caddy `request_body { max_size 1MB }` caps what PHP reads; a truncated signed body fails HMAC); HMAC is checked before JSON parsing |
| Unsigned webhooks left on in production | Container compile fails when `allow_unsigned_webhooks: true` and `app_secret` is empty unless `kernel.debug` is true (always in `prod`); the flag must be a literal boolean (no `%env()%` bypass) |
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
| 7 | No secrets in logs | Logs carry profile, masked phone (`*******1111`), message type, Graph ids and HTTP / Graph codes only; `SecretRedactor` + VarDumper casters + `#[SensitiveParameter]` keep tokens out of dumps, exception messages and trace arguments ([Logging](#logging-req-obs-001)); the profiler HttpClient panel is a documented dev-only residual | New log calls reviewed for tokens, bodies and full phone numbers |
| 8 | Cryptography | Only HMAC-SHA256 (`hash_hmac`) for webhook signatures with constant-time `hash_equals`; no custom crypto, no secret storage | No new crypto primitives |
| 9 | Permissions / exposure | Single public HTTP surface: `/whatsapp/webhook[/{profile}]`, authenticated by HMAC (machine endpoint, no session / CSRF). Unknown profiles answer exactly like a bad token / signature (403, same body: no profile enumeration); a signed payload whose `metadata.phone_number_id` is not the profile's phone number id → 403. Signatures must carry the `sha256=` prefix. `allow_unsigned_webhooks: true` without `app_secret` is refused at container compile time unless `kernel.debug` is true (never in `prod`); the flag must be a literal boolean, and its effective value is forced to `false` at compile time outside debug kernels and in `prod`, so an `%env()%` `app_secret` that is empty at runtime still rejects unsigned POSTs. All bundle services are private | Route list unchanged (`debug:router`); no new public services |
| 10 | Limits / DoS | Graph calls: profile `timeout` + `max_duration` + cURL connect timeout, `max_redirects: 0` (REQ-RUNTIME-001); mock transport records at most 100 entries; host body-size limit for the webhook (demo: Caddy `request_body { max_size 1MB }`) and FrankenPHP `max_wait_time` | Timeout hierarchy still valid ([CONFIGURATION.md](CONFIGURATION.md#timeouts-req-runtime-001)) |
| 11 | AI security audit (REQ-SEC-004) | Latest recorded grade below ([AI security audit](#ai-security-audit-req-sec-004)) and in `BUNDLES_SECURITY_ANALYSIS.md` | Grade **Pass**; re-audit when the webhook, HTTP or DI surface changes |

## AI security audit (REQ-SEC-004)

| Field | Value |
| ----- | ----- |
| Risk level | **Low** |
| AI audit date | 2026-10-30 (Appendix BU, 1.3.0 `def402c`); previous 2026-09-14 (Appendix W, 1.1.0) |
| Method | Independent adversarial review of `src/` and `v1.2.2..def402c` with PoCs (trace / profiler token leak, media URL policy fuzz, env-secret guard) |
| Grade | **Pass (good)** |
| Findings fixed after BU | L1 token in exception trace args; L3 env `app_secret` bypass of the unsigned guard; L4 payload not bound to the profile phone number id; I1 permanent 4xx retried; I2 optional `sha256=` prefix; I3 profile enumeration (404 vs 403); I4 default `demo-verify` token; I8 logger reference |
| Open residuals | L2 profiler HttpClient panel (dev); non-idempotent Messenger retries (I1); webhook replay window (protocol); no media size cap (I5); `uploadMedia()` paths trusted (I7) — see [Residual risks](#residual-risks) |

## Residual risks

| Residual | Why it remains | Host guidance |
|----------|----------------|---------------|
| Symfony profiler HttpClient panel shows the bearer token (audit BU L2) | In debug, `http_client` is a `TraceableHttpClient`; the data collector stores the request options and the curl verbose log (request headers), whatever way the header is set. Dev only | Use a test / short-lived token in dev, never share or expose profiler data (`/_profiler`), disable it where real tokens are used (`framework.profiler.enabled: false` or `framework.profiler.collect: false`) |
| Webhook replay (audit BU L4) | Meta signatures carry no timestamp: a captured signed body can be replayed. The bundle binds payloads to the profile phone number id, but cannot expire them | De-duplicate on message id (`IncomingMessage::$id`) / status id; Meta also retries deliveries |
| Non-idempotent Messenger retries (audit BU I1) | The Cloud API has no idempotency key: a timeout after Meta accepted a message makes a retry send it twice. Permanent 4xx (except 429) are `UnrecoverableMessageHandlingException` (not retried) | Keep `max_retries` low; see [USAGE.md](USAGE.md#idempotency) |
| No media size cap (audit BU I5) | `downloadMedia()` buffers the Meta-hosted file in memory (WhatsApp allows up to 100 MB), bounded by the profile `timeout` only. URLs are Meta-controlled (allow-list) | Download media in Messenger workers with enough `memory_limit`, not in web requests |
| `uploadMedia($filePath)` reads any local path (audit BU I7) | The path is passed to `DataPart::fromPath()` and the file is sent to Meta | Never pass user-controlled paths; upload only files your app wrote |

