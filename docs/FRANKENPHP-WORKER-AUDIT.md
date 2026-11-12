# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/whatsapp-business-bundle` (`symfony-bundle`) |
| Audited revision | pre-`v1.1.1` (worker hardening on top of `v1.1.0` / `fd82ed9`) |
| Reviewed version | `v1.3.0` (2026-11-12 review: `git diff v1.3.0 -- src` is empty at this commit) |
| Audit date | 2026-09-23 (reconfirmed 2026-09-25 for release) |
| Method | Manual review of every file under `src/` (clients, transports, Graph HTTP client, webhook controller, Messenger handler, commands, DI extension, `Resources/config/*.yaml`) |
| Remediation (2026-09-23) | W-01 resolved (mock transport cleared per main request + bounded); W-02 and W-03 accepted |
| **Verdict** | ✅ **100% viable under scenario B** (`reset_kernel` / services resetter off) — Cloud API profiles are stateless; mock profiles are cleared at the start of every main request and bounded, so they no longer depend on `kernel.reset` |

## Table of contents

- [Execution model assumed](#execution-model-assumed)
- [Summary](#summary)
- [Services reviewed](#services-reviewed)
- [Findings](#findings)
  - [W-01 — `MockTransport` keeps every recorded send; only `kernel.reset` clears it (Medium)](#w-01--mocktransport-keeps-every-recorded-send-only-kernelreset-clears-it-medium)
  - [W-02 — VarDumper casters are registered in a process-global static map at boot (Info)](#w-02--vardumper-casters-are-registered-in-a-process-global-static-map-at-boot-info)
  - [W-03 — Media downloads are loaded fully into memory (Info)](#w-03--media-downloads-are-loaded-fully-into-memory-info)
- [Kept-alive kernel test (REQ-RUNTIME-002)](#kept-alive-kernel-test-req-runtime-002)
- [Usage recommendations in worker mode](#usage-recommendations-in-worker-mode)
- [Re-audit triggers](#re-audit-triggers)

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | `MockTransport::$sent` is cleared per main request and capped at 100 entries (W-01, resolved); every other service is `readonly` or has no properties |
| Static properties / `static` locals | ✅ | No own static properties; one write to VarDumper's global `AbstractCloner::$defaultCasters` at bundle boot (W-02, Info) |
| `ResetInterface` / `kernel.reset` coverage | ✅ | `MockTransport` implements `ResetInterface` and is tagged `kernel.reset`; `ResetMockTransportsOnRequestSubscriber` also calls `reset()` on `kernel.request` (main requests, priority 4096) |
| Request / user / locale captured in services | ✅ | `WebhookController` receives `Request` as an `__invoke()` argument; nothing is stored |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | None used; credentials are compiled into `CloudApiProfile` definitions |
| Doctrine / EntityManager | ✅ N/A | No persistence |
| Output, headers, `exit`, shutdown functions | ✅ | None; the controller returns `Response` objects |
| Resources (files, sockets, cURL) held open | ✅ | Only the shared Symfony `http_client`; `DataPart::fromPath()` is created per upload |
| Memory growth across requests | ✅ | Mock profiles bounded to 100 entries and cleared per request (W-01, resolved); none for Cloud API profiles |
| Blocking I/O and timeouts | ✅ | Every Graph call sets `timeout`, `max_duration`, `max_redirects: 0` and a cURL connect timeout, all configurable per profile |
| Third-party static state | ✅ Info | VarDumper casters (W-02); Symfony HttpClient / Mime / Messenger are used through services |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` included in `phpstan.neon.dist` |

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `nowo_whatsapp_business.profile.<name>` (`CloudApiProfile`) | yes | none (`final readonly`) | ✅ | ✅ |
| `nowo_whatsapp_business.graph.<name>` (`GraphApiClient`) | yes | none (`final readonly`) | ✅ | ✅ |
| `nowo_whatsapp_business.transport.<name>` (`CloudApiTransport`) | yes | none (`final readonly`) | ✅ | ✅ |
| `nowo_whatsapp_business.transport.<name>` (`MockTransport`) | yes | `$sent` list, bounded (`src/Transport/MockTransport.php`) | ✅ (reset via `kernel.reset`) | ✅ cleared per main request (W-01 resolved) |
| `ResetMockTransportsOnRequestSubscriber` (only when a mock profile exists) | yes | none (`final readonly`, iterator of already-instantiated mock transports) | ✅ | ✅ |
| `nowo_whatsapp_business.client.<name>` (`WhatsappClient`) | yes | none (`final readonly`) | ✅ | ✅ |
| `nowo_whatsapp_business.registry` (`WhatsappClientRegistry`) | yes | none (`final readonly`, client map built at compile time) | ✅ | ✅ |
| `WebhookController` | yes (`controller.service_arguments`) | none (`readonly` dependencies) | ✅ | ✅ |
| `WebhookVerifier`, `WebhookParser` | yes | none | ✅ | ✅ |
| `SendOutgoingMessageHandler` | yes | none (`final readonly`) | ✅ | ✅ |
| `SendTextCommand`, `ListTemplatesCommand` | yes (CLI only) | none | ✅ N/A | ✅ N/A |

Messages, webhook DTOs and templates under `src/Message/`, `src/Webhook/` and `src/Template/` are `final readonly` value objects created per call. Events under `src/Event/` are created per dispatch and never stored by the bundle. `SecretRedactor` and `MediaDownloadUrlPolicy` only have pure static methods and constants.

## Findings

### W-01 — `MockTransport` keeps every recorded send; only `kernel.reset` clears it (Medium)

- **Where:** `src/Transport/MockTransport.php` (`$sent`), appended via `record()` from `send()` / `markAsRead()`, exposed by `recorded()`, cleared by `reset()`. The `kernel.reset` tag is added in `WhatsappBusinessExtension::registerProfile()`. The default configuration uses `transport: mock` when no profile is defined (`Configuration.php`).
- **Worker impact (pre-fix):** under **A** the services resetter emptied the list after each request. Under **B** the list never cleared: unbounded growth and cross-request leakage of phone numbers / Graph bodies via `recorded()`.
- **Status:** Resolved — `ResetMockTransportsOnRequestSubscriber` (`kernel.request`, priority 4096, main requests only) resets every already-instantiated mock transport, and `MockTransport` keeps at most `DEFAULT_MAX_RECORDED` (100) entries (optional `$maxRecorded` constructor argument). Regression test: `tests/Unit/Transport/MockTransportWorkerModeTest.php` (two requests on the same instances without `reset()`).

### W-02 — VarDumper casters are registered in a process-global static map at boot (Info)

- **Where:** `src/WhatsappBusinessBundle.php:20-24` calls `SensitiveDumpRegistrar::register()`, which writes `AbstractCloner::$defaultCasters[$class]` (`src/Http/SensitiveDumpRegistrar.php:45-49`).
- **Worker impact:** runs once per worker boot (or per kernel boot). The keys are a fixed list of 8 classes and the value is a static callable, so it holds no request data and repeated calls overwrite the same keys. It is harmless and actually protects secrets in `dump()` / profiler output.
- **Recommendation:** none. Keep the list fixed and never store per-request data in this map.
- **Status:** Accepted — boot-time, idempotent, fixed-size and holds no request data.

### W-03 — Media downloads are loaded fully into memory (Info)

- **Where:** `src/Http/GraphApiClient.php:90-116` (`downloadRaw()` returns `getContent()`), used by `CloudApiTransport::downloadMedia()` (`src/Transport/CloudApiTransport.php:52-60`).
- **Worker impact:** a large media file raises the peak memory of that request. The string is not kept by any service, so it is freed after the request; there is no leak. The download is bounded by the profile `timeout` / `max_duration` and `max_redirects: 0`.
- **Recommendation:** size `memory_limit` for the largest expected media, or download large media in a Messenger worker instead of inside an HTTP request.
- **Status:** Accepted — per-request peak only, not retained; changing `downloadMedia()` to streaming would break the `MediaDownload` API.

No other findings. HTTP timeouts are explicit and configurable (`timeout` default 10 s, `connect_timeout` default 5 s in `src/DependencyInjection/Configuration.php:81-90`), which keeps a slow Graph API from pinning worker threads indefinitely.

## Kept-alive kernel test (REQ-RUNTIME-002)

**Safe with kernel reset false.** `tests/Integration/Worker/WorkerKeptAliveKernelTest.php::testWebhooksInOneKernelWithoutResetterKeepTenantsSeparatedAndMemoryFlat` boots ONE kernel (two mock profiles, `alpha` and `beta`, each with its own app secret, verify token and phone number id) and drives `http_kernel` `handle()` + `terminate()` 1000 times alternating the two tenants, never calling `services_resetter` or `kernel.reset`. Per iteration it checks the GET challenge (own verify token accepted, the other tenant's refused), a POST signed with the tenant's own secret (accepted; a listener sees only that tenant's message and replies through the registry client, so the tenant's mock transport holds exactly that one reply, proving `ResetMockTransportsOnRequestSubscriber` clears it without `kernel.reset`), and that the other tenant's secret or a payload for the other phone number id is refused with nothing dispatched. After a 50-iteration warm-up and a GC pass, memory growth must stay under 4 MiB.

Rule 7 (host Doctrine): the bundle has no persistence and never touches the host default EntityManager; nothing here clears, flushes or closes it.

## Usage recommendations in worker mode

- Configure at least one profile with `transport: cloud_api` in production. The built-in default profile uses the mock transport.
- Keep `kernel.reset` / the services resetter enabled (the Symfony default); mock profiles no longer depend on it, but host services may.
- Tune `timeout` and `connect_timeout` per profile to the lowest acceptable values, and cap waiting requests with FrankenPHP `max_wait_time`. For bulk sending, dispatch `SendOutgoingMessage` through Messenger so the HTTP worker does not block on Graph.
- Custom `TransportInterface` implementations or event listeners on `MessageSentEvent` / `IncomingMessageEvent` must not store messages in properties, or must implement `ResetInterface` and bound what they keep.
- The demo in `demo/symfony8/` runs FrankenPHP in worker mode (`docker/frankenphp/Caddyfile` has a `worker` block).

## Re-audit triggers

Re-run this audit when a change adds: properties to `WhatsappClient`, `GraphApiClient`, `CloudApiTransport`, the registry or the webhook controller; a template or media cache; new entries in `SensitiveDumpRegistrar`; a new transport or a change to `MockTransport` recording; an event listener or subscriber inside the bundle; or any use of `$_SERVER` / `$_ENV` at runtime.
