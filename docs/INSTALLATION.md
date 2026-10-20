# Installation

## Table of contents

- [Requirements](#requirements)
- [Composer](#composer)
- [Verify](#verify)

## Requirements

- PHP >= 8.2, < 8.6
- Symfony 6.4+ / 7.0+ / 8.0 / 8.1 (see root `composer.json`)
- Composer 2

## Composer

```bash
composer require nowo-tech/whatsapp-business-bundle
```

With Symfony Flex, the recipe registers the bundle, copies `config/packages/nowo_whatsapp_business.yaml`, and imports webhook routes.

Without Flex, enable the bundle:

```php
// config/bundles.php
return [
    // ...
    Nowo\WhatsappBusinessBundle\WhatsappBusinessBundle::class => ['all' => true],
];
```

Import routes:

```yaml
# config/routes/nowo_whatsapp_business.yaml
nowo_whatsapp_business:
    resource: '@WhatsappBusinessBundle/Resources/config/routing.yaml'
```

Then add configuration (see [CONFIGURATION.md](CONFIGURATION.md)). Enable `framework.http_client` if it is not already on.

## Verify

```bash
php bin/console debug:container WhatsappClientInterface
```
