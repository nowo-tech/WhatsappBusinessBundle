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
| `allow_unsigned_webhooks` | `false` | Demo only; never enable in production |

## Environment variables

Documented in the Flex recipe. Do not commit `.env` files with real tokens.

## Timeouts (REQ-RUNTIME-001)

Keep PHP `max_execution_time` and reverse-proxy write timeouts **greater** than `timeout`. Under FrankenPHP worker mode, a hung Graph call would otherwise pin a worker.

## Webhooks

- `GET /whatsapp/webhook` — subscription challenge
- `POST /whatsapp/webhook` — inbound messages and statuses
- `…/whatsapp/webhook/{profile}` — same, for a named profile

Production: set `app_secret` and `allow_unsigned_webhooks: false`.

Media downloads (`downloadMedia`) only follow HTTPS URLs on Meta hosts (`*.facebook.com`, `*.fbsbx.com`, `*.fbcdn.net`, …) or the host of `graph_base_uri`. Query strings on dumped media URLs are redacted. Media ids passed to `downloadMedia()` must match `^[0-9A-Za-z_-]+$` (otherwise `InvalidMediaIdException`); HTTP errors and non-JSON Graph responses are reported as `GraphApiException`.
