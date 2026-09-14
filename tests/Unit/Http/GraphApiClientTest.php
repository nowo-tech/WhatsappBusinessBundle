<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Http;

use Nowo\WhatsappBusinessBundle\Config\CloudApiProfile;
use Nowo\WhatsappBusinessBundle\Config\TransportKind;
use Nowo\WhatsappBusinessBundle\Http\GraphApiClient;
use Nowo\WhatsappBusinessBundle\Http\GraphApiException;
use Nowo\WhatsappBusinessBundle\Tests\Unit\Support\ProfileFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(GraphApiClient::class)]
final class GraphApiClientTest extends TestCase
{
    public function testPostGetDeleteAndError(): void
    {
        $client = new GraphApiClient(new MockHttpClient([
            new MockResponse('{"ok":true}', ['http_code' => 200]),
            new MockResponse('{"data":[]}', ['http_code' => 200]),
            new MockResponse('{}', ['http_code' => 200]),
            new MockResponse('{"error":{"message":"bad","code":10,"error_subcode":2,"fbtrace_id":"abc"}}', ['http_code' => 400]),
        ]), ProfileFactory::cloud());

        self::assertSame(['ok' => true], $client->postJson('123456/messages', ['to' => '1']));
        self::assertSame(['data' => []], $client->get('waba/message_templates'));
        self::assertSame([], $client->delete('MEDIA'));

        $this->expectException(GraphApiException::class);
        $client->postJson('123456/messages', []);
    }

    public function testTransportFailure(): void
    {
        $http = new MockHttpClient(static function (): ResponseInterface {
            throw new class('down') extends RuntimeException implements TransportExceptionInterface {};
        });
        $client = new GraphApiClient($http, ProfileFactory::cloud());
        $this->expectException(GraphApiException::class);
        $this->expectExceptionMessage('WhatsApp Cloud API transport failure.');
        $client->get('x');
    }

    public function testDownloadAndMedia(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'wa');
        self::assertIsString($tmp);
        file_put_contents($tmp, 'file');
        $http = new MockHttpClient([
            new MockResponse('{"id":"MID"}', ['http_code' => 200]),
            new MockResponse('binary', ['http_code' => 200]),
        ]);
        $client = new GraphApiClient($http, ProfileFactory::cloud());
        self::assertSame('MID', $client->postMedia('123456/media', $tmp, 'image/jpeg')['id']);
        self::assertSame('binary', $client->downloadRaw('https://graph.facebook.com/v21.0/file'));
        unlink($tmp);
    }

    public function testDownloadTransportFailure(): void
    {
        $http = new MockHttpClient(static function (): ResponseInterface {
            throw new class('down') extends RuntimeException implements TransportExceptionInterface {};
        });
        $client = new GraphApiClient($http, ProfileFactory::cloud());
        $this->expectException(GraphApiException::class);
        $client->downloadRaw('https://graph.facebook.com/v21.0/file');
    }

    public function testDownloadRejectsOffAllowlistWithoutCallingHttp(): void
    {
        $http = new MockHttpClient(static function (): never {
            self::fail('must not send the access token to a rejected URL');
        });
        $client = new GraphApiClient($http, ProfileFactory::cloud());
        $this->expectException(GraphApiException::class);
        $this->expectExceptionMessage('WhatsApp media download URL is not allowed.');
        $client->downloadRaw('https://evil.example/steal');
    }

    public function testDownloadAllowsLookasideHost(): void
    {
        $http   = new MockHttpClient([new MockResponse('bin', ['http_code' => 200])]);
        $client = new GraphApiClient($http, ProfileFactory::cloud());
        self::assertSame('bin', $client->downloadRaw('https://lookaside.fbsbx.com/whatsapp_business/attachments/x'));
    }

    public function testDownloadAllowsConfiguredGraphHost(): void
    {
        $profile = new CloudApiProfile(
            name: 'default',
            phoneNumberId: '123456',
            accessToken: 'token',
            transport: TransportKind::Cloud,
            whatsappBusinessAccountId: 'waba-1',
            graphVersion: 'v21.0',
            graphBaseUri: 'https://graph.internal.test',
            timeout: 10.0,
            connectTimeout: 5.0,
            appSecret: 'app-secret',
            webhookVerifyToken: 'verify-me',
            allowUnsignedWebhooks: false,
        );
        $http   = new MockHttpClient([new MockResponse('proxy', ['http_code' => 200])]);
        $client = new GraphApiClient($http, $profile);
        self::assertSame('proxy', $client->downloadRaw('https://graph.internal.test/v21.0/file'));
    }

    public function testNonArrayHeadersAreReset(): void
    {
        $http = new MockHttpClient([
            new MockResponse('{"ok":1}', ['http_code' => 200]),
        ]);
        $client = new GraphApiClient($http, ProfileFactory::cloud());
        $ref    = new ReflectionMethod($client, 'request');
        $result = $ref->invoke($client, 'GET', 'x', ['headers' => 'nope']);
        self::assertSame(['ok' => 1], $result);
    }
}
