<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Http;

use Nowo\WhatsappBusinessBundle\Http\MediaDownloadUrlPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MediaDownloadUrlPolicy::class)]
final class MediaDownloadUrlPolicyTest extends TestCase
{
    #[DataProvider('allowedProvider')]
    public function testAllowsMetaAndConfiguredHosts(string $url, string $graphBaseUri): void
    {
        self::assertTrue(MediaDownloadUrlPolicy::isAllowed($url, $graphBaseUri));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function allowedProvider(): iterable
    {
        $graph = 'https://graph.facebook.com';

        yield 'graph' => ['https://graph.facebook.com/v21.0/123', $graph];
        yield 'lookaside' => ['https://lookaside.fbsbx.com/whatsapp_business/attachments/', $graph];
        yield 'fbcdn' => ['https://scontent.xx.fbcdn.net/v/t.jpg', $graph];
        yield 'instagram' => ['https://lookaside.instagram.com/file', $graph];
        yield 'configured proxy' => ['https://graph.internal.test/v21.0/m', 'https://graph.internal.test'];
        yield 'configured subdomain' => ['https://cdn.graph.internal.test/m', 'https://graph.internal.test'];
    }

    #[DataProvider('rejectedProvider')]
    public function testRejectsOffAllowlist(string $url, string $graphBaseUri): void
    {
        self::assertFalse(MediaDownloadUrlPolicy::isAllowed($url, $graphBaseUri));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rejectedProvider(): iterable
    {
        $graph = 'https://graph.facebook.com';

        yield 'http' => ['http://lookaside.fbsbx.com/x', $graph];
        yield 'evil host' => ['https://evil.example/steal', $graph];
        yield 'userinfo' => ['https://token:x@lookaside.fbsbx.com/x', $graph];
        yield 'user only' => ['https://token@lookaside.fbsbx.com/x', $graph];
        yield 'suffix trick' => ['https://lookaside.fbsbx.com.evil.test/x', $graph];
        yield 'empty host' => ['https:path', $graph];
        yield 'parse false' => ['https://', $graph];
        yield 'not a url' => ['not-a-url', $graph];
    }
}
