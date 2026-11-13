# WhatsApp Business Bundle — baseline spec

**Package**: `nowo-tech/whatsapp-business-bundle`  
**Configuration root**: `nowo_whatsapp_business`  
**Reviewed**: 2026-10-20  
**Related docs**: [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/USAGE.md`](../../docs/USAGE.md)  
**Code inventory**: [`code-inventory.md`](code-inventory.md)

## Summary

Host Symfony applications need a reusable, worker-safe client for the public WhatsApp Cloud API (Graph): outbound messages, media, templates, and Meta webhooks. Persistence and CRM remain in the host. The bundle guarantees named profiles with HTTP timeouts, typed outbound messages plus `sendRaw()`, HMAC-verified webhooks dispatched as events, a Messenger message for async sends, two console commands, and a mock transport for demos/tests.

## Goals

- Named profiles (`default_profile` + `profiles`) with HTTP timeouts (REQ-RUNTIME-001).
- Typed outbound messages plus `sendRaw()` for host-built Graph JSON.
- HMAC webhook verification and events for inbound messages/statuses.
- Mock transport for demos/tests; worker-safe under FrankenPHP even when the kernel is not reset (`ResetInterface` + per-request clear + bounded buffer).
- No host-proprietary code, no Doctrine schema, no secrets in git.

## Non-goals

- Back-office CRUD, Twig message composers, or WhatsApp template CMS.
- Wrapping unofficial third-party WhatsApp libraries as the public API.
- Persisting messages, contacts or conversations (host-owned).
- Demo applications under `demo/` are illustrative, not Packagist API.

## User Scenarios (`US-*`)

### US-01 — Send template message (P1)

As a Symfony app, I send a WhatsApp template through a named profile with HTTP timeouts and masked logging.

**Acceptance**: **Given** a configured profile, **When** I send a `TemplateMessage` through `WhatsappClientRegistryInterface::get($profile)`, **Then** the Graph `messages` endpoint is called with the template payload and a `MessageResult` is returned; secrets never appear in logs or dumps.

### US-02 — Verify webhook (P1)

As a Symfony app, I accept Meta webhooks only when `X-Hub-Signature-256` validates against the configured app secret.

**Acceptance**: **Given** a webhook POST, **When** the HMAC does not match, **Then** `WebhookVerificationException` is raised and no event is dispatched; **When** it matches, **Then** `WebhookReceivedEvent`, `IncomingMessageEvent` and `StatusUpdateEvent` are dispatched.

### US-03 — Send raw Graph JSON and media (P2)

As a Symfony app, I send media, reactions, locations or host-built Graph payloads (`sendRaw()`), and download inbound media only from Meta hosts.

**Acceptance**: **Given** a media download URL outside the Meta allowlist, **When** the download is requested, **Then** it is refused and the access token is not sent.

### US-04 — Async send and CLI (P3)

As an operator, I queue `SendOutgoingMessage` through Messenger and use `whatsapp:send-text` / `whatsapp:templates` from the console.

**Acceptance**: **Given** Messenger routing, **When** a `SendOutgoingMessage` is handled, **Then** the message is sent through the named profile and `MessageSentEvent` / `MessageSendFailedEvent` is dispatched.

### US-05 — Demo / tests with the mock transport (P3)

As a developer, I use the mock transport so no message leaves the machine and recordings do not leak between worker requests.

**Acceptance**: **Given** a profile using the `mock` transport, **When** two main requests run in one worker, **Then** the second request sees an empty `recorded()` buffer.

## Functional requirements (`FR-*`)

### Bundle, configuration and profiles (`FR-CFG-*`)

- **FR-CFG-001**: Profiles use `default_profile` + `profiles` with HTTPS-only Graph base URIs (`CloudApiProfile`, `TransportKind`).
- **FR-CFG-002**: The extension wires services, console commands, Messenger handler and webhook routes from `Resources/config/*.yaml`.

### Client and HTTP transport (`FR-HTTP-*`)

- **FR-HTTP-001**: Outbound HTTP uses connect/request timeouts (REQ-RUNTIME-001) through `GraphApiClient`; Graph errors raise `GraphApiException`.
- **FR-HTTP-002**: `WhatsappClient` / `WhatsappClientRegistry` expose typed sends, `sendRaw()`, media and template operations per named profile; unknown profiles raise `UnknownProfileException`.
- **FR-HTTP-003**: Secrets are redacted from logs and dumps (`SecretRedactor`, `SensitiveDumpRegistrar`), and media download URLs are restricted to Meta hosts (`MediaDownloadUrlPolicy`).
- **FR-HTTP-004**: `TransportInterface` with `CloudApiTransport` (real Graph) and `MockTransport` (demos/tests).
- **FR-HTTP-005**: A host can serve a profile with its own `TransportInterface` implementation through `transport_service` (`transport: custom`), validated at compile time (REQ-ARCH-001 rule 5).

### Messages and templates (`FR-MSG-*`)

- **FR-MSG-001**: Typed outbound messages (text, template, media, location, reaction, Graph payload, raw Graph) wrapped in `OutgoingEnvelope`; value objects validate phone numbers and media ids (`InvalidPhoneNumberException`, `InvalidMediaIdException`).
- **FR-MSG-002**: `MessageResult`, `MediaDownload` and `MessageTemplate` / `TemplateStatus` model API results.

### Webhooks and events (`FR-HOOK-*`)

- **FR-HOOK-001**: Webhook HMAC verification is required unless explicitly disabled for local demos (`WebhookVerifier`).
- **FR-HOOK-002**: `WebhookController` (routes `/whatsapp/webhook` and `/whatsapp/webhook/{profile}`) parses notifications (`WebhookParser`) into `IncomingMessage` / `StatusUpdate` and dispatches events under `Nowo\WhatsappBusinessBundle\Event`.

### Messenger and console (`FR-ASYNC-*`)

- **FR-ASYNC-001**: `SendOutgoingMessage` + handler send asynchronously and dispatch `MessageSentEvent` / `MessageSendFailedEvent`.
- **FR-ASYNC-002**: Console commands `whatsapp:send-text` and `whatsapp:templates`.

### Runtime (`FR-RUN-*`)

- **FR-RUN-001**: Mock transport does not leak recorded sends between worker requests (`ResetInterface` / `kernel.reset`, plus `ResetMockTransportsOnRequestSubscriber` on main `kernel.request`, and a bounded `recorded()` buffer).

### Errors (`FR-ERR-*`)

- **FR-ERR-001**: All bundle exceptions extend `WhatsappBusinessException`; a profile without a business account id raises `MissingBusinessAccountException` for template operations.

## Public API

- `WhatsappClientInterface` / `WhatsappClientRegistryInterface`
- Config alias `nowo_whatsapp_business`
- Routes `/whatsapp/webhook` and `/whatsapp/webhook/{profile}`
- Events under `Nowo\WhatsappBusinessBundle\Event`
- Messenger `SendOutgoingMessage`
- Console `whatsapp:send-text`, `whatsapp:templates`

## Success criteria (`SC-*`)

- **SC-001**: **60/60** files tracked under `src/` are mapped in [`code-inventory.md`](code-inventory.md) (audit: `git ls-files src | wc -l`).
- **SC-002**: PHPUnit line coverage of `src/` stays at 100% (`make test-coverage`); PHPStan FrankenPHP classic + worker rulesets pass.
- **SC-003**: `make release-check` passes (includes `check-no-cursor-coauthor`).
- **SC-004**: The demo answers on `http://localhost:8031` with the mock transport (`make demo-smoke`).

## Validation commands

```bash
make qa              # cs-check + phpstan + PHPUnit (Docker)
make test-coverage   # PHPUnit with coverage
make release-check   # full pre-release gate
```
