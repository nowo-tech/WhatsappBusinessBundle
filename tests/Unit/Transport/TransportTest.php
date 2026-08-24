<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Transport;

use Nowo\WhatsappBusinessBundle\Exception\MissingBusinessAccountException;
use Nowo\WhatsappBusinessBundle\Http\GraphApiClient;
use Nowo\WhatsappBusinessBundle\Tests\Unit\Support\ProfileFactory;
use Nowo\WhatsappBusinessBundle\Transport\CloudApiTransport;
use Nowo\WhatsappBusinessBundle\Transport\MockTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(MockTransport::class)]
#[CoversClass(CloudApiTransport::class)]
final class TransportTest extends TestCase
{
    public function testMockTransportRecordsAndResets(): void
    {
        $transport = new MockTransport(ProfileFactory::mock());
        $sent      = $transport->send(['to' => '34611', 'type' => 'text']);
        self::assertStringStartsWith('mock.', $sent['messages'][0]['id']);
        $transport->markAsRead('wamid.1');
        self::assertStringStartsWith('mock-media.', $transport->uploadMedia('/tmp/x', 'image/jpeg')->id);
        self::assertSame('mock-bytes', $transport->downloadMedia('m1')->contents);
        self::assertSame('hello_world', $transport->listTemplates()[0]->name);
        self::assertNotSame([], $transport->recorded());
        $transport->reset();
        self::assertSame([], $transport->recorded());

        $emptyTo = $transport->send(['type' => 'text']);
        self::assertSame('', $emptyTo['contacts'][0]['wa_id']);
    }

    public function testMockListTemplatesRequiresWaba(): void
    {
        $this->expectException(MissingBusinessAccountException::class);
        (new MockTransport(ProfileFactory::mock(waba: null)))->listTemplates();
    }

    public function testCloudTransportRoundTrip(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'wa');
        self::assertIsString($tmp);
        file_put_contents($tmp, 'x');
        $http = new MockHttpClient([
            new MockResponse('{"messages":[{"id":"wamid.1"}],"contacts":[{"wa_id":"34611"}]}', ['http_code' => 200]),
            new MockResponse('{}', ['http_code' => 200]),
            new MockResponse('{"id":"MID"}', ['http_code' => 200]),
            new MockResponse('{"url":"https://graph.facebook.com/v21.0/file","mime_type":"image/jpeg"}', ['http_code' => 200]),
            new MockResponse('IMG', ['http_code' => 200]),
            new MockResponse('{"data":[{"id":"t1","name":"hello_world","status":"APPROVED","language":"en_US","category":"UTILITY"}]}', ['http_code' => 200]),
            new MockResponse('{"data":"nope"}', ['http_code' => 200]),
        ]);
        $graph     = new GraphApiClient($http, ProfileFactory::cloud());
        $transport = new CloudApiTransport($graph, ProfileFactory::cloud());
        $sent      = $transport->send(['to' => '34611', 'type' => 'text']);
        self::assertSame('wamid.1', $sent['messages'][0]['id']);
        $transport->markAsRead('wamid.1');
        self::assertSame('MID', $transport->uploadMedia($tmp, 'image/jpeg')->id);
        $download = $transport->downloadMedia('MID');
        self::assertSame('IMG', $download->contents);
        self::assertSame('hello_world', $transport->listTemplates()[0]->name);
        self::assertSame([], $transport->listTemplates());
        unlink($tmp);
    }

    public function testDownloadWithoutUrl(): void
    {
        $http      = new MockHttpClient([new MockResponse('{"id":"MID"}', ['http_code' => 200])]);
        $transport = new CloudApiTransport(new GraphApiClient($http, ProfileFactory::cloud()), ProfileFactory::cloud());
        $download  = $transport->downloadMedia('MID');
        self::assertSame('', $download->contents);
        self::assertNull($download->url);
    }
}
