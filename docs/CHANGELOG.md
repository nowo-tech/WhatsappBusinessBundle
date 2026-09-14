# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
