<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Http;

use Nowo\WhatsappBusinessBundle\Http\SecretRedactor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecretRedactor::class)]
final class SecretRedactorTest extends TestCase
{
    public function testRedactStringTokens(): void
    {
        self::assertSame(
            'bad ' . SecretRedactor::PLACEHOLDER,
            SecretRedactor::redactString('bad EAAG12345678zzzz'),
        );
        self::assertSame(
            'Authorization: Bearer ' . SecretRedactor::PLACEHOLDER,
            SecretRedactor::redactString('Authorization: Bearer tok-value'),
        );
        self::assertSame(
            'access_token=' . SecretRedactor::PLACEHOLDER . '&x=1',
            SecretRedactor::redactString('access_token=super-secret&x=1'),
        );
    }

    public function testRedactArray(): void
    {
        $out = SecretRedactor::redactArray([
            'access_token' => 'plain-secret',
            'nested'       => ['app_secret' => 's', 'msg' => 'ok EAAG12345678zzzz'],
            'list_item'    => 'keep-list',
            'n'            => 3,
            'b'            => true,
            'z'            => null,
        ]);
        self::assertSame(SecretRedactor::PLACEHOLDER, $out['access_token']);
        self::assertIsArray($out['nested']);
        self::assertSame(SecretRedactor::PLACEHOLDER, $out['nested']['app_secret']);
        self::assertSame('ok ' . SecretRedactor::PLACEHOLDER, $out['nested']['msg']);
        self::assertSame('keep-list', $out['list_item']);
        self::assertSame(3, $out['n']);
        self::assertTrue($out['b']);
        self::assertNull($out['z']);
    }

    public function testMaskPhone(): void
    {
        self::assertSame('', SecretRedactor::maskPhone(''));
        self::assertSame('**', SecretRedactor::maskPhone('12'));
        self::assertSame('****', SecretRedactor::maskPhone('1234'));
        self::assertSame('*******1111', SecretRedactor::maskPhone('34611111111'));
    }

    public function testRedactUrl(): void
    {
        self::assertNull(SecretRedactor::redactUrl(null));
        self::assertSame('', SecretRedactor::redactUrl(''));
        self::assertSame(
            'https://lookaside.fbsbx.com/p',
            SecretRedactor::redactUrl('https://lookaside.fbsbx.com/p'),
        );
        self::assertSame(
            'https://lookaside.fbsbx.com/p?' . SecretRedactor::PLACEHOLDER,
            SecretRedactor::redactUrl('https://lookaside.fbsbx.com/p?oh=abc&oe=def'),
        );
        self::assertSame(SecretRedactor::PLACEHOLDER, SecretRedactor::redactUrl('not-a-host'));
        self::assertSame(SecretRedactor::PLACEHOLDER, SecretRedactor::redactUrl('https://'));
        self::assertSame(SecretRedactor::PLACEHOLDER, SecretRedactor::redactUrl('https:path'));
    }
}
