<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Config;

use Nowo\WhatsappBusinessBundle\Config\CloudApiProfile;
use Nowo\WhatsappBusinessBundle\Exception\MissingBusinessAccountException;
use Nowo\WhatsappBusinessBundle\Tests\Unit\Support\ProfileFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CloudApiProfile::class)]
final class CloudApiProfileTest extends TestCase
{
    public function testPaths(): void
    {
        $profile = ProfileFactory::cloud();
        self::assertSame('123456/messages', $profile->messagesPath());
        self::assertSame('123456/media', $profile->mediaPath());
        self::assertSame('waba-1/message_templates', $profile->templatesPath());
        $info = $profile->__debugInfo();
        self::assertSame('[redacted]', $info['accessToken']);
        self::assertSame('[redacted]', $info['appSecret']);
        self::assertSame('[redacted]', $info['webhookVerifyToken']);
    }

    public function testDebugInfoKeepsNullSecrets(): void
    {
        $info = ProfileFactory::mock(appSecret: null, verifyToken: null)->__debugInfo();
        self::assertNull($info['appSecret']);
        self::assertNull($info['webhookVerifyToken']);
    }

    public function testTemplatesPathRequiresWaba(): void
    {
        $this->expectException(MissingBusinessAccountException::class);
        ProfileFactory::mock(waba: null)->templatesPath();
    }

    public function testTemplatesPathRejectsEmptyWaba(): void
    {
        $this->expectException(MissingBusinessAccountException::class);
        ProfileFactory::mock(waba: '')->templatesPath();
    }
}
