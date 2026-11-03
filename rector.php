<?php

declare(strict_types=1);

/**
 * Rector configuration for WhatsApp Business Bundle.
 *
 * Ensures PHP 8.2+ and Symfony 6|7|8 compatibility; applies dead code, code quality,
 * and type declaration rules. Processes src/ and tests/.
 *
 * @see https://getrector.com/documentation
 */
use Rector\Config\RectorConfig;
use Rector\Symfony\CodeQuality\Rector\Class_\EventSubscriberToAttributeRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withComposerBased(symfony: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    )
    ->withSkip([
        // rector 2.8.3 (symfony set): a bundle must not rely on the host app's autoconfiguration for
        // #[AsEventListener]; the subscriber is wired explicitly in the service config.
        EventSubscriberToAttributeRector::class,
        __DIR__ . '/demo',
        __DIR__ . '/vendor',
    ]);
