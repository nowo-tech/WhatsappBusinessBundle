<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Webhook;

use Nowo\WhatsappBusinessBundle\Exception\WebhookVerificationException;
use Nowo\WhatsappBusinessBundle\Tests\Unit\Support\ProfileFactory;
use Nowo\WhatsappBusinessBundle\Webhook\WebhookVerifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhookVerifier::class)]
final class WebhookVerifierTest extends TestCase
{
    public function testSubscribe(): void
    {
        $verifier = new WebhookVerifier();
        $profile  = ProfileFactory::mock();
        self::assertTrue($verifier->verifySubscribe($profile, 'subscribe', 'verify-me'));
        self::assertFalse($verifier->verifySubscribe($profile, 'subscribe', 'nope'));
        self::assertFalse($verifier->verifySubscribe($profile, 'unsubscribe', 'verify-me'));
        self::assertFalse($verifier->verifySubscribe(ProfileFactory::mock(verifyToken: null), 'subscribe', 'x'));
        self::assertFalse($verifier->verifySubscribe(ProfileFactory::mock(verifyToken: ''), 'subscribe', ''));
    }

    public function testSignature(): void
    {
        $verifier = new WebhookVerifier();
        $profile  = ProfileFactory::mock();
        $body     = '{"object":"whatsapp_business_account"}';
        $header   = 'sha256=' . hash_hmac('sha256', $body, 'app-secret');
        $verifier->verifySignature($profile, $body, $header);
        $verifier->verifySignature($profile, $body, hash_hmac('sha256', $body, 'app-secret'));
        $this->expectException(WebhookVerificationException::class);
        $verifier->verifySignature($profile, $body, 'sha256=deadbeef');
    }

    public function testMissingSignatureWhenSecretConfigured(): void
    {
        $this->expectException(WebhookVerificationException::class);
        (new WebhookVerifier())->verifySignature(ProfileFactory::mock(), '{}', null);
    }

    public function testUnsignedAllowed(): void
    {
        (new WebhookVerifier())->verifySignature(
            ProfileFactory::mock(allowUnsigned: true, appSecret: null),
            '{}',
            null,
        );
        $this->addToAssertionCount(1);
    }

    public function testUnsignedRejectedWhenNotAllowed(): void
    {
        $this->expectException(WebhookVerificationException::class);
        (new WebhookVerifier())->verifySignature(
            ProfileFactory::mock(allowUnsigned: false, appSecret: ''),
            '{}',
            null,
        );
    }

    public function testEmptyHeader(): void
    {
        $this->expectException(WebhookVerificationException::class);
        (new WebhookVerifier())->verifySignature(ProfileFactory::mock(), '{}', '');
    }
}
