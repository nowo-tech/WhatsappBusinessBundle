# Security

## Table of contents

- [Reporting a vulnerability](#reporting-a-vulnerability)
- [Threat model (this bundle)](#threat-model-this-bundle)
- [Logging (REQ-OBS-001)](#logging-req-obs-001)
- [Supported versions](#supported-versions)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)

## Reporting a vulnerability

Please report security issues privately via GitHub Security Advisories on [`nowo-tech/WhatsappBusinessBundle`](https://github.com/nowo-tech/WhatsappBusinessBundle) or email the maintainers listed in `composer.json`. Do not open a public issue for undisclosed vulnerabilities.

Also see [`.github/SECURITY.md`](../.github/SECURITY.md).

## Threat model (this bundle)

| Risk | Mitigation |
|------|------------|
| Stolen Cloud API access token | Tokens only via env/`%env()%`; never logged; never committed |
| Forged webhooks | HMAC `X-Hub-Signature-256` with `app_secret`; `allow_unsigned_webhooks` defaults false |
| SSRF via Graph base URI | Config allows `https://` URLs only |
| Hung outbound HTTP under FrankenPHP worker | Profile `timeout` + `connect_timeout` (REQ-RUNTIME-001) |
| PII in logs | Phone numbers masked; message bodies and tokens never logged |
| Mock transport leaking across worker requests | `ResetInterface` / `kernel.reset` |

## Logging (REQ-OBS-001)

- **info** — send success: profile, masked `to`, message type, Graph message id
- **error** — send/transport failure: profile, HTTP status, Graph error code (not the token)
- **warning** — webhook signature rejection / unknown profile
- **info** — webhook accepted: counts only

## Supported versions

Security fixes are provided for the latest minor of the current major release line. See [`.github/SECURITY.md`](../.github/SECURITY.md#supported-versions).

## Release security checklist (12.4.1)

- No secrets in git (tokens, `.env`, app secrets)
- `composer audit` in CI
- Webhook HMAC required in production profiles
- Coverage + PHPStan clean before tag

## AI security audit (REQ-SEC-004)

| Field | Value |
| ----- | ----- |
| Risk level | **Low** |
| AI audit date | 2026-08-24 |
| Method | Cursor agent static review of `src/`, DI config, webhook controller, transport, `docs/SECURITY.md`, `.github/SECURITY.md` |
| Grade | **Pass (good)** |
| Open residuals | Host must protect webhook routes and store tokens only in env/`%env()%`; no Critical/High findings. |
