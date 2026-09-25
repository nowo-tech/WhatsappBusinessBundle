# WhatsApp Business Bundle — baseline spec

## Problem

Host Symfony applications need a reusable, worker-safe client for the public WhatsApp Cloud API (Graph): outbound messages, media, templates, and Meta webhooks. Persistence and CRM remain in the host.

## Goals

- Named profiles (`default_profile` + `profiles`) with HTTP timeouts (REQ-RUNTIME-001).
- Typed outbound messages plus `sendRaw()` for host-built Graph JSON.
- HMAC webhook verification and events for inbound messages/statuses.
- Mock transport for demos/tests; worker-safe under FrankenPHP even when the kernel is not reset (`ResetInterface` + per-request clear + bounded buffer).
- No host-proprietary code, no Doctrine schema, no secrets in git.

## Non-goals

- Back-office CRUD, Twig message composers, or WhatsApp template CMS.
- Wrapping unofficial third-party WhatsApp libraries as the public API.

## User Scenarios

### US-01 — Send template message (P1)

As a Symfony app, I send a WhatsApp template through a named profile with HTTP timeouts and masked logging.

### US-02 — Verify webhook (P1)

As a Symfony app, I accept Meta webhooks only when `X-Hub-Signature-256` validates against the configured app secret.

## Functional requirements

- FR-001: Profiles use `default_profile` + `profiles` with HTTPS-only Graph base URIs.
- FR-002: Outbound HTTP uses connect/request timeouts (REQ-RUNTIME-001).
- FR-003: Webhook HMAC verification is required unless explicitly disabled for local demos.
- FR-004: Mock transport does not leak recorded sends between worker requests (`ResetInterface` / `kernel.reset`, plus `ResetMockTransportsOnRequestSubscriber` on main `kernel.request`, and a bounded `recorded()` buffer).

## Public API

- `WhatsappClientInterface` / `WhatsappClientRegistryInterface`
- Config alias `nowo_whatsapp_business`
- Routes `/whatsapp/webhook` and `/whatsapp/webhook/{profile}`
- Events under `Nowo\WhatsappBusinessBundle\Event`
- Messenger `SendOutgoingMessage`
- Console `whatsapp:send-text`, `whatsapp:templates`
