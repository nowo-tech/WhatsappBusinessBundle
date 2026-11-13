# WhatsApp Business Bundle

[![CI](https://github.com/nowo-tech/WhatsappBusinessBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/WhatsappBusinessBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/whatsapp-business-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/whatsapp-business-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/whatsapp-business-bundle.svg)](https://packagist.org/packages/nowo-tech/whatsapp-business-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-6.4%20%7C%207.4%2B%20%7C%208.0%20%7C%208.1%2B-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/WhatsappBusinessBundle.svg?style=social&label=Star)](https://github.com/nowo-tech/WhatsappBusinessBundle) [![Coverage](https://img.shields.io/badge/Coverage-100%25-brightgreen)](#tests-and-coverage)

> ⭐ **Found this useful?** [Install from Packagist](https://packagist.org/packages/nowo-tech/whatsapp-business-bundle) · Give it a **star** on [GitHub](https://github.com/nowo-tech/WhatsappBusinessBundle) so more developers can find it.

**Symfony bundle for the WhatsApp Cloud API (WhatsApp Business).** Named profiles, typed outbound messages, HMAC-signed webhooks, optional Messenger queuing, and FrankenPHP worker-safe HTTP timeouts. The host application owns persistence (conversations, templates, files); this bundle is the Cloud API transport.

> 📋 **Compatible with Symfony 6.4+, 7.x, and 8.x**

![FrankenPHP Friendly Worker Mode](docs/images/frankenphp-friendly.png)

This bundle is **FrankenPHP worker mode friendly**.

## Table of contents

- [What is this?](#what-is-this)
- [Quick Search Terms](#quick-search-terms)
- [Features](#features)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Requirements](#requirements)
- [Configuration](#configuration)
- [Host application integration](#host-application-integration)
- [Documentation](#documentation)
- [Tests and coverage](#tests-and-coverage)
- [License](#license)
- [Contributing](#contributing)
- [Author](#author)

## What is this?

A reusable Symfony client for [WhatsApp Cloud API](https://developers.facebook.com/docs/whatsapp/cloud-api):

- Send text, templates, media, location, reactions, and host-built Graph JSON (`sendRaw`)
- Verify Meta webhooks (`hub.verify_token` + `X-Hub-Signature-256`)
- Dispatch events so the host can persist inbound messages and statuses
- Mock transport for demos and tests (no Meta credentials)

## Quick Search Terms

Looking for: **WhatsApp Business API**, **WhatsApp Cloud API**, **Symfony WhatsApp**, **Graph API messages**, **WhatsApp webhooks**, **message templates**, **FrankenPHP WhatsApp**? This bundle is the Cloud API layer.

## Features

- Named profiles (`default_profile` + `profiles`) with phone number id, access token, WABA id, Graph version, and timeouts
- `WhatsappClientInterface` / `WhatsappClientRegistryInterface` (constructor injection)
- Typed messages (`TextMessage`, `TemplateMessage`, `MediaMessage`, …) plus `sendRaw()` for existing Graph payloads
- Signed webhooks at `/whatsapp/webhook` and `/whatsapp/webhook/{profile}`
- Events: `MessageSentEvent`, `MessageSendFailedEvent`, `WebhookReceivedEvent`, `IncomingMessageEvent`, `StatusUpdateEvent`
- Optional Messenger message `SendOutgoingMessage` (failures bubble to the failure transport)
- CLI: `whatsapp:send-text`, `whatsapp:templates`
- Mock transport worker-safe without `kernel.reset` (`ResetInterface` + per-request clear + bounded `recorded()`)
- Logs never include access tokens, app secrets, or full phone numbers (masked)
- Dump/profiler redaction + media URL allowlist (anti-SSRF) for Cloud API downloads

## Installation

```bash
composer require nowo-tech/whatsapp-business-bundle
```

See [docs/INSTALLATION.md](docs/INSTALLATION.md).

## Quick start

```yaml
# config/packages/nowo_whatsapp_business.yaml
nowo_whatsapp_business:
    default_profile: default
    profiles:
        default:
            transport: cloud
            phone_number_id: '%env(WHATSAPP_PHONE_NUMBER_ID)%'
            access_token: '%env(WHATSAPP_ACCESS_TOKEN)%'
            timeout: 10.0
            connect_timeout: 5.0
```

```php
use Nowo\WhatsappBusinessBundle\Client\WhatsappClientInterface;

final class NotifyCustomer
{
    public function __construct(
        private readonly WhatsappClientInterface $whatsapp,
    ) {
    }

    public function ping(string $to): void
    {
        $this->whatsapp->sendText($to, 'Hello from Cloud API');
    }
}
```

## Requirements

- PHP >= 8.2, < 8.6
- Symfony 6.4+, 7.0+, 8.0, or 8.1 (see `composer.json`; CI exercises 6.4, 7.0, 7.4, 8.0, and 8.1)
- A WhatsApp Business Cloud API app (phone number id + access token) for the `cloud` transport

## Configuration

Named profiles are documented in [docs/CONFIGURATION.md](docs/CONFIGURATION.md). Timeouts follow REQ-RUNTIME-001 (`timeout` + `connect_timeout` on every Graph call); under FrankenPHP keep PHP `max_execution_time` and the Caddy write timeout above them and cap the thread queue with `max_wait_time` ([timeout hierarchy](docs/CONFIGURATION.md#timeouts-req-runtime-001)).

## Host application integration

This bundle does **not** ship Doctrine entities, CRM templates, or back-office screens. A host Symfony app that already talks to Cloud API can:

1. Map existing env vars (phone number id, access token, WABA id) onto `nowo_whatsapp_business.profiles`.
2. Inject `WhatsappClientRegistryInterface` and call `get('default')` / `get('test')`.
3. Keep domain persistence in the host. Subscribe to `IncomingMessageEvent` / `StatusUpdateEvent` to store webhooks.
4. Use `sendRaw($to, $graphBody)` when the host already builds Graph JSON.
5. Point Meta’s webhook URL at `/whatsapp/webhook` (or `/whatsapp/webhook/{profile}`) and set `app_secret` + `webhook_verify_token`.

Do not copy proprietary host code into this package. The public API is the integration surface.

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
  - [Host architecture: standard Symfony and DDD / hexagonal (REQ-ARCH-001)](docs/USAGE.md#host-architecture-standard-symfony-and-ddd--hexagonal-req-arch-001)
- [Contributing](docs/CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)
- [GitHub Spec Kit](docs/SPEC-KIT.md)

### Additional documentation

- [PSR evaluation (REQ-CS-007)](docs/PSR.md)
- [Demo (Symfony 8)](demo/README.md) — run `make -C demo up-symfony8` from the bundle root.
- [Demo with FrankenPHP (development and production)](docs/DEMO-FRANKENPHP.md)
- [FrankenPHP worker mode audit](docs/FRANKENPHP-WORKER-AUDIT.md)
- [GitHub Actions CI requirements](docs/GITHUB_CI.md)

## Tests and coverage

- Tests: PHPUnit (PHP)
- PHP: 100%
- TS/JS: N/A
- Python: N/A

## License

MIT. See [LICENSE](LICENSE).

## Contributing

See [docs/CONTRIBUTING.md](docs/CONTRIBUTING.md).

## Author

Héctor Franco Aceituno · [Nowo.tech](https://nowo.tech)
