<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Http;

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
        $client->downloadRaw('https://example.com/x');
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
