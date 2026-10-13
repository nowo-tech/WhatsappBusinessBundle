# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.0] - 2026-10-13

### Security

- `downloadMedia()` (Cloud API transport) rejects media ids that do not match `^[0-9A-Za-z_-]+$` with `InvalidMediaIdException`, so a crafted id can no longer address another Graph path (e.g. `../PHONE_ID/messages`) with the profile access token.

### Fixed

- Non-JSON Graph responses (e.g. an HTML 502 page from a proxy) now throw `GraphApiException` (with the HTTP status) instead of leaking a `DecodingException`; `send()` therefore dispatches `MessageSendFailedEvent` for them too.
- Media downloads answering 3xx/4xx/5xx now throw `GraphApiException` (`httpStatus` set) instead of a raw HttpClient exception.

[1.2.0]: https://github.com/nowo-tech/WhatsappBusinessBundle/releases/tag/v1.2.0

## [1.1.4] - 2026-10-13

### Dependencies

- `igor-php/igor-php` 0.9.7 → 0.10.1 (require-dev constraint + development lock; #11).

[1.1.4]: https://github.com/nowo-tech/WhatsappBusinessBundle/releases/tag/v1.1.4

## [1.1.3] - 2026-10-09

### Changed

- Dev dependencies (lock refresh): PHPStan 2.3.1 (+ phpunit/symfony extensions 2.1), PHPUnit 11.5.57, Rector 2.7.0, `nowo-tech/phpstan-frankenphp` 1.2.3.
- Demo (Symfony 8): Twig 3.30.0, `twig/extra-bundle` 3.29.0, HotReloadBundle 1.5.5, TwigInspectorBundle 1.1.7.

[1.1.3]: https://github.com/nowo-tech/WhatsappBusinessBundle/releases/tag/v1.1.3

## [1.1.2] - 2026-09-27

### Added

- **REQ-CS-008:** `igor-php/igor-php` (require-dev only), root `igor.json`, Composer/`Makefile` `igor` target, and `release-check` wiring for FrankenPHP worker-state audit.

### Changed

- **Worker safety (Igor):** justified `// @igor-ignore` annotations and/or `ResetInterface` / request-scoped fixes so `make igor` passes on package `src/`.

[1.1.2]: https://github.com/nowo-tech/WhatsappBusinessBundle/releases/tag/v1.1.2

## [1.1.1] - 2026-09-25

### Fixed

- FrankenPHP worker mode without `kernel.reset`: sends recorded by `MockTransport` (the default transport when no profile is configured) no longer leak into later requests or grow for the life of the worker. A new `ResetMockTransportsOnRequestSubscriber` clears already-instantiated mock transports at the start of every main request, and `MockTransport` keeps at most `DEFAULT_MAX_RECORDED` (100) entries (optional `$maxRecorded` constructor argument).

### Notes

- See [UPGRADING.md](UPGRADING.md) and [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

[1.1.1]: https://github.com/nowo-tech/WhatsappBusinessBundle/releases/tag/v1.1.1

## [1.1.0] - 2026-09-14

### Security

- Redact access tokens, app secrets, and webhook verify tokens from `var_dump` / Symfony VarDumper / profiler dumps (`__debugInfo`, `SensitiveParameter`, optional VarDumper casters).
- Refuse Cloud API media downloads whose URL is not HTTPS on Meta hosts (or the configured `graph_base_uri` host); do not follow redirects (prevents Bearer-token SSRF).
- Do not put transport exception messages or Graph payloads into logs; sanitize Graph error messages that echo `EAA…` tokens.
- Webhook HTTP error bodies are generic (no exception text).
- Zero-config default profile now has `allow_unsigned_webhooks: false`.
- Profile / Graph / transport / client service definitions are private in the container (public aliases `nowo_whatsapp_business.client` and typed interfaces remain for injection).

### Added

- `SecretRedactor`, `MediaDownloadUrlPolicy`, and `SensitiveDumpRegistrar` helpers.
- `GraphApiException::transportFailure()` and `GraphApiException::mediaUrlRejected()`.

### Changed

- Outbound Graph / media HTTP requests use `max_redirects: 0`.

### Notes

- See [UPGRADING.md](UPGRADING.md) for integrator steps from 1.0.x.

[1.1.0]: https://github.com/nowo-tech/WhatsappBusinessBundle/releases/tag/v1.1.0

## [1.0.1] - 2026-09-07

### Changed

- **Dependency:** bump `symfony/service-contracts`.
- **Deps (dev/CI):** phpstan / rector / php-cs-fixer and GitHub Actions bumps.

### Notes

- **No API or configuration changes** for integrators.

[1.0.1]: https://github.com/nowo-tech/WhatsappBusinessBundle/releases/tag/v1.0.1

## [1.0.0] - 2026-08-24

### Added

- Initial public release: WhatsApp Cloud API client with named profiles, typed outbound messages, signed webhooks, mock transport, Messenger handler, and CLI commands.
