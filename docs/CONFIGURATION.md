# Configuration

## Table of contents

- [Named profiles](#named-profiles)
- [Profile keys](#profile-keys)
- [Host transport (`transport_service`)](#host-transport-transport_service)
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
| `transport` | `cloud` | `cloud` (Graph API), `mock` (in-memory) or `custom` (the service in `transport_service`) |
| `transport_service` | `null` | Service id of a host `TransportInterface`; setting it implies `transport: custom` ([below](#host-transport-transport_service)) |
| `phone_number_id` | required | Cloud API sender phone number id |
| `access_token` | required | Permanent / system-user token. **Never log or commit.** |
| `whatsapp_business_account_id` | `null` | Required for `listTemplates()` |
| `graph_version` | `v21.0` | Must match `vMAJOR.MINOR` |
| `graph_base_uri` | `https://graph.facebook.com` | `https://` only |
| `timeout` | `10.0` | HttpClient idle + `max_duration` (seconds) |
| `connect_timeout` | `5.0` | Applied via curl `CURLOPT_CONNECTTIMEOUT` when curl is available |
| `app_secret` | `null` | HMAC key for `X-Hub-Signature-256` |
| `webhook_verify_token` | `null` | GET `hub.verify_token` (the zero-config mock profile no longer ships `demo-verify`) |
| `allow_unsigned_webhooks` | `false` | Demo only; literal boolean; with an empty `app_secret` it is refused unless `kernel.debug` is true (never in `prod`) |

## Host transport (`transport_service`)

Since **1.4** a profile can be served by the host's own transport instead of the Graph API or the in-memory mock
(REQ-ARCH-001 rule 5). Implement `Nowo\WhatsappBusinessBundle\Transport\TransportInterface` — its signatures use
only bundle value objects (`MediaId`, `MediaDownload`, `MessageTemplate`) and arrays, no framework types — and name
the service:

```yaml
nowo_whatsapp_business:
    profiles:
        gateway:
            transport_service: App\Whatsapp\OnPremiseTransport
            phone_number_id: '%env(WHATSAPP_PHONE_NUMBER_ID)%'
            access_token: '%env(WHATSAPP_ACCESS_TOKEN)%'
```

```php
namespace App\Whatsapp;

use Nowo\WhatsappBusinessBundle\Message\MediaDownload;
use Nowo\WhatsappBusinessBundle\Message\MediaId;
use Nowo\WhatsappBusinessBundle\Transport\TransportInterface;

final readonly class OnPremiseTransport implements TransportInterface
{
    /**
     * @param array<string, mixed> $graphBody
     *
     * @return array<string, mixed>
     */
    public function send(array $graphBody): array
    {
        // … your own gateway; return a Graph-shaped payload (messages[0].id at least).
    }

    public function markAsRead(string $messageId): void { /* … */ }

    public function uploadMedia(string $filePath, string $mimeType): MediaId { /* … */ }

    public function downloadMedia(string $mediaId): MediaDownload { /* … */ }

    /** @return list<\Nowo\WhatsappBusinessBundle\Template\MessageTemplate> */
    public function listTemplates(): array { /* … */ }
}
```

Rules:

- `transport_service` alone is enough: the profile becomes `custom` without repeating `transport: custom`. Writing both
  is fine; `transport_service` with any other `transport` value is refused at configuration time, and `transport:
  custom` without `transport_service` as well.
- The service id is resolved at compile time. An unknown id, or a class that does not implement `TransportInterface`,
  fails the build with a message naming the profile (`TransportServicePass`). A factory-built or synthetic definition
  is left to the container's own type checks.
- The bundle **aliases** the service, it never redefines, retags or decorates it: `shared` / `public` and every other
  setting stay as the host declared them. The alias id is `nowo_whatsapp_business.transport.<profile>`.
- No `GraphApiClient` is built for a `custom` profile, so it needs no `http_client` and `graph_version`,
  `graph_base_uri`, `timeout` and `connect_timeout` are unused (they keep their defaults).
- `phone_number_id` and `access_token` stay required (they are part of `CloudApiProfile`, which the bundle passes to
  listeners and dumps). Use placeholders when your gateway does not need them — `access_token` is still redacted from
  every dump and log.
- **Worker mode (REQ-RUNTIME-002):** the bundle does **not** reset a host transport — only `mock` profiles get the
  per-request reset. Your transport must keep nothing per request, or reset itself (a bundle `kernel.reset` tag is not
  applied to host services, and under FrankenPHP with `kernel.reset: false` the resetter does not run at all).
- `cloud` and `mock` are unchanged: the defaults, the wiring and the behaviour are exactly what they were before 1.4.

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
- `X-Hub-Signature-256` must be `sha256=<hex>` (a bare digest is rejected)
- A signed payload whose `entry[].changes[].value.metadata.phone_number_id` differs from the profile `phone_number_id` is rejected (403) and no event is dispatched
- Unknown profile names get the same 403 response as a bad token / signature

Production: set `app_secret` and `allow_unsigned_webhooks: false`. The container refuses to compile when a profile has `allow_unsigned_webhooks: true` without `app_secret` unless `kernel.debug` is true, and always in the `prod` environment (fail closed: staging / preprod kernels without debug are refused too). The value must be a literal boolean; `%env()%` is rejected so a runtime variable cannot bypass the check.

Routes are declared with `#[Route]` attributes on `WebhookController` (REQ-SF-004); `@WhatsappBusinessBundle/Resources/config/routing.yaml` is a thin attribute import kept for backward compatibility (route names `nowo_whatsapp_business_webhook` and `nowo_whatsapp_business_webhook_profile` are unchanged). The routes are opt-in: the controller service is not autoconfigured, so a Symfony 7.3+ host `controllers: { resource: routing.controllers }` import does not expose the webhook unless you import the bundle routing.

Media downloads (`downloadMedia`) only follow HTTPS URLs on Meta hosts (`*.facebook.com`, `*.fbsbx.com`, `*.fbcdn.net`, …) or the host of `graph_base_uri`. Query strings on dumped media URLs are redacted. Media ids passed to `downloadMedia()` must match `^[0-9A-Za-z_-]+$` (otherwise `InvalidMediaIdException`); HTTP errors and non-JSON Graph responses are reported as `GraphApiException`.
