# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.2.2] - 2026-10-13](#122---2026-10-13)
- [[1.2.1] - 2026-10-13](#121---2026-10-13)
- [[1.2.0] - 2026-10-13](#120---2026-10-13)
- [[1.1.4] - 2026-10-13](#114---2026-10-13)
- [[1.1.3] - 2026-10-09](#113---2026-10-09)
- [[1.1.2] - 2026-09-27](#112---2026-09-27)
- [[1.1.1] - 2026-09-25](#111---2026-09-25)
- [[1.1.0] - 2026-09-14](#110---2026-09-14)
- [[1.0.1] - 2026-09-07](#101---2026-09-07)
- [[1.0.0] - 2026-08-24](#100---2026-08-24)

## [Unreleased]

### Security

- The container refuses to compile in the `prod` environment when a profile sets `allow_unsigned_webhooks: true` without `app_secret` (a copied dev config would otherwise accept forged webhooks on a public endpoint).
- `docs/SECURITY.md`: the release security checklist (12.4.1) now covers all REQ-SEC-002 items (SECURITY docs, `.env`, secrets, recipe, input/output, `composer audit`, logs, crypto, exposure, limits/DoS) plus the REQ-SEC-004 audit row; threat model lists oversized webhook bodies and the prod guard.

### Changed

- Dependencies: `symfony/mime` lock bumped (bundle 7.4.22, demo 8.1.10) for CVE-2026-45070 so `composer audit --locked` is clean.
- Webhook routes are declared with `#[Route]` attributes on `WebhookController` (REQ-SF-004). `Resources/config/routing.yaml` is now a thin attribute import kept for backward compatibility; route names, paths, methods and requirements are unchanged.
- `SendOutgoingMessageHandler` is registered with `#[AsMessageHandler]` instead of an explicit `messenger.message_handler` tag (REQ-MSG-001); USAGE documents transport expectations, retries, failure transport, idempotency and logging.
- PHPDoc with an English description on every public method in `src/` (REQ-CS-001).
- PHPStan: the empty `phpstan-baseline.neon` is removed; `phpstan.neon.dist` states `ignoreErrors: []` (REQ-CS-006).
- Coverage: `make coverage-check` / `make test-coverage-100` targets (REQ-TEST-006); `make test-coverage` and the CI coverage step now fail below 100% line coverage (REQ-TEST-003).
- Demo: `make -C demo release-check` runs `update-bundle` first (composer update + dump-autoload + `cache:clear`, REQ-DEMO-007); the DNS block in `docker-compose.yml` is commented (REQ-DEMO-009); both Caddyfiles set the timeout hierarchy (PHP `max_execution_time` / `max_input_time` 30 s, Caddy write 35 s, FrankenPHP `max_wait_time` 15 s) and a 1 MB request body cap (REQ-RUNTIME-001). CONFIGURATION / DEMO-FRANKENPHP / README document the hierarchy; a test covers Graph timeouts.
- `docs/GITHUB_CI.md` records the local gate run used as CI evidence while GitHub Actions cannot run on this private repository (REQ-CI-003).
- Cursor rules: removed the frontend TS/Vite rule (no frontend); Twig, tests and docs rules aligned with this repository (REQ-IDE-004).
- Docs: USAGE states that host and demo forms are Symfony Form Types rendered with `form_start` / the `not child.rendered` child loop / `form_end`, and that raw `<form>` / `<input>` must not be reintroduced; `check-no-raw-html-form` enforces it in `release-check` (REQ-TWIG-005 rule 9).
- Developer tooling: `make release-check` runs the full REQ-MAKE-002 chain in order (`ensure-up` first, then `check-no-cursor-coauthor`, `check-open-prs`, new `check-no-raw-html-form` (REQ-TWIG-005), `composer-sync`, `cs-fix`, `cs-check`, `rector-dry`, `phpstan`, `igor`, `test-coverage`, demo release-check).
- Dependencies refreshed (Composer and pnpm/npm lock files of the bundle and its demos, campaign 2026-10-19).
- `.php-cs-fixer.dist.php`: `new ParallelConfig(2)` (REQ-CS-001).
- Documentation: wave-1 REQ compliance — README documentation list order and GitHub stars badge, canonical issue templates (`---feature-request.md`), GitHub CI requirements doc from the shared template, CONTRIBUTING Cursor Agent settings, TOCs, DEMO-FRANKENPHP dev/prod start and troubleshooting, Engram ↔ spec-driven cross-reference, spec-driven development rewrite, full GitHub Spec Kit manual, constitution title, baseline spec (US-*, grouped FR-*, SC-*, validation) and categorized inventory (60/60).

## [1.2.2] - 2026-10-13

### Security

- `downloadMedia()` media id check now anchors at the true end of the string (PCRE `D` modifier): `"123\n"` was still accepted by `^[0-9A-Za-z_-]+$` and reached the Graph URL. `docs/SECURITY.md` threat model lists the media id validation.

### Changed

- CI: the per-Symfony install step no longer re-requires `symfony/yaml` with `--dev` (it is a runtime dependency since 1.2.1 and was moved to `require-dev` in every matrix cell). The matrix already covers the `^6.4` floor (6.4, 7.0, 7.4, 8.0, 8.1).

[1.2.2]: https://github.com/nowo-tech/WhatsappBusinessBundle/releases/tag/v1.2.2

## [1.2.1] - 2026-10-13

### Changed

- `symfony/yaml` moved from `require-dev` to `require`: the extension loads `services.yaml` with `YamlFileLoader`, so apps without `symfony/yaml` failed at container build.

[1.2.1]: https://github.com/nowo-tech/WhatsappBusinessBundle/releases/tag/v1.2.1

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
