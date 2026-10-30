# Configuration

## Table of contents

- [Named profiles](#named-profiles)
- [Profile keys](#profile-keys)
- [Environment variables](#environment-variables)
- [Timeouts (REQ-RUNTIME-001)](#timeouts-req-runtime-001)
- [Webhooks](#webhooks)

## Named profiles

```yaml
nowo_whatsapp_business:
    default_profile: default
    profiles:
        default:
            transport: cloud
            phone_number_id: '%env(WHATSAPP_PHONE_NUMBER_ID)%'
            access_token: '%env(WHATSAPP_ACCESS_TOKEN)%'
            whatsapp_business_account_id: '%env(default::WHATSAPP_BUSINESS_ACCOUNT_ID)%'
            graph_version: 'v21.0'
            graph_base_uri: 'https://graph.facebook.com'
            timeout: 10.0
            connect_timeout: 5.0
            app_secret: '%env(default::WHATSAPP_APP_SECRET)%'
            webhook_verify_token: '%env(default::WHATSAPP_WEBHOOK_VERIFY_TOKEN)%'
            allow_unsigned_webhooks: false
```

`default_profile` must match a key under `profiles`. Inject `WhatsappClientInterface` for the default profile, or `WhatsappClientRegistryInterface` to select by name.

## Profile keys

| Key | Default | Notes |
| --- | ------- | ----- |
| `transport` | `cloud` | `cloud` (Graph API) or `mock` (in-memory) |
| `phone_number_id` | required | Cloud API sender phone number id |
| `access_token` | required | Permanent / system-user token. **Never log or commit.** |
| `whatsapp_business_account_id` | `null` | Required for `listTemplates()` |
| `graph_version` | `v21.0` | Must match `vMAJOR.MINOR` |
| `graph_base_uri` | `https://graph.facebook.com` | `https://` only |
| `timeout` | `10.0` | HttpClient idle + `max_duration` (seconds) |
| `connect_timeout` | `5.0` | Applied via curl `CURLOPT_CONNECTTIMEOUT` when curl is available |
| `app_secret` | `null` | HMAC key for `X-Hub-Signature-256` |
| `webhook_verify_token` | `null` | GET `hub.verify_token` |
| `allow_unsigned_webhooks` | `false` | Demo only; refused in `prod` when `app_secret` is empty |

## Environment variables

Documented in the Flex recipe. Do not commit `.env` files with real tokens.

## Timeouts (REQ-RUNTIME-001)

Every Graph request (`postJson`, `get`, `delete`, `postMedia`) and every media download sets HttpClient `timeout` and `max_duration` to the profile `timeout`, `max_redirects: 0`, and (with cURL) `CURLOPT_CONNECTTIMEOUT` to `connect_timeout`. A timeout surfaces as `GraphApiException::transportFailure()` (HTTP status `0`); the token is never logged.

Layer the deadlines so the innermost fires first (REQ-RUNTIME-001):

| Layer | Example | Rule |
|-------|---------|------|
| Profile `timeout` / `connect_timeout` | `10.0` / `5.0` | Fires first |
| PHP `max_execution_time` / `max_input_time` | `30` | Strictly **greater** than `timeout` |
| Reverse proxy / Caddy `servers.timeouts.write` | `35s` | Strictly **greater** than PHP |
| FrankenPHP `max_wait_time` (worker or classic) | `15s` | Caps the wait for a free PHP thread (504 instead of an endless queue) |

Under FrankenPHP (worker mode in particular) a hung Graph call would otherwise pin a PHP thread. When raising `timeout`, raise PHP and the HTTP write deadline in the same change. For bulk sends, route `SendOutgoingMessage` to an async Messenger transport ([USAGE.md](USAGE.md#messenger)) so web threads never wait on Graph. The demo Caddyfiles are a working example ([DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md#timeouts-req-runtime-001)).

## Webhooks

- `GET /whatsapp/webhook` — subscription challenge
- `POST /whatsapp/webhook` — inbound messages and statuses
- `…/whatsapp/webhook/{profile}` — same, for a named profile

Production: set `app_secret` and `allow_unsigned_webhooks: false`. The container refuses to compile in the `prod` environment when a profile has `allow_unsigned_webhooks: true` without `app_secret` (fail closed).

Routes are declared with `#[Route]` attributes on `WebhookController` (REQ-SF-004); `@WhatsappBusinessBundle/Resources/config/routing.yaml` is a thin attribute import kept for backward compatibility (route names `nowo_whatsapp_business_webhook` and `nowo_whatsapp_business_webhook_profile` are unchanged).

Media downloads (`downloadMedia`) only follow HTTPS URLs on Meta hosts (`*.facebook.com`, `*.fbsbx.com`, `*.fbcdn.net`, …) or the host of `graph_base_uri`. Query strings on dumped media URLs are redacted. Media ids passed to `downloadMedia()` must match `^[0-9A-Za-z_-]+$` (otherwise `InvalidMediaIdException`); HTTP errors and non-JSON Graph responses are reported as `GraphApiException`.
