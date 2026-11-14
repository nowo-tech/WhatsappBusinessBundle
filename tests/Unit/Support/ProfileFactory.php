<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Support;

use Nowo\WhatsappBusinessBundle\Config\CloudApiProfile;
use Nowo\WhatsappBusinessBundle\Config\TransportKind;

use function assert;

final class ProfileFactory
{
    /**
     * @param non-empty-string $name
     */
    public static function mock(
        string $name = 'default',
        ?string $waba = 'waba-1',
        bool $allowUnsigned = true,
        ?string $appSecret = 'app-secret',
        ?string $verifyToken = 'verify-me',
    ): CloudApiProfile {
        assert($name !== '');

        return new CloudApiProfile(
            name: $name,
            phoneNumberId: '123456',
            accessToken: 'token',
            transport: TransportKind::Mock,
            whatsappBusinessAccountId: $waba,
            graphVersion: 'v21.0',
            graphBaseUri: 'https://graph.facebook.com',
            timeout: 10.0,
            connectTimeout: 5.0,
            appSecret: $appSecret,
            webhookVerifyToken: $verifyToken,
            allowUnsignedWebhooks: $allowUnsigned,
        );
    }

    /**
     * @param non-empty-string $name
     */
    public static function cloud(string $name = 'default'): CloudApiProfile
    {
        assert($name !== '');

        return new CloudApiProfile(
            name: $name,
            phoneNumberId: '123456',
            accessToken: 'token',
            transport: TransportKind::Cloud,
            whatsappBusinessAccountId: 'waba-1',
            graphVersion: 'v21.0',
            graphBaseUri: 'https://graph.facebook.com',
            timeout: 10.0,
            connectTimeout: 5.0,
            appSecret: 'app-secret',
            webhookVerifyToken: 'verify-me',
            allowUnsignedWebhooks: false,
        );
    }

    /**
     * A `custom` profile (host transport, REQ-ARCH-001 rule 5).
     *
     * @param non-empty-string $name
     */
    public static function custom(string $name = 'default', string $accessToken = 'token'): CloudApiProfile
    {
        assert($name !== '');

        return new CloudApiProfile(
            name: $name,
            phoneNumberId: '123456',
            accessToken: $accessToken,
            transport: TransportKind::Custom,
            whatsappBusinessAccountId: null,
            graphVersion: 'v21.0',
            graphBaseUri: 'https://graph.facebook.com',
            timeout: 10.0,
            connectTimeout: 5.0,
            appSecret: null,
            webhookVerifyToken: null,
            allowUnsignedWebhooks: false,
        );
    }
}
