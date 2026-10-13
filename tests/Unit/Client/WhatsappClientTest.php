<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Client;

use Nowo\WhatsappBusinessBundle\Client\WhatsappClient;
use Nowo\WhatsappBusinessBundle\Event\MessageSendFailedEvent;
use Nowo\WhatsappBusinessBundle\Event\MessageSentEvent;
use Nowo\WhatsappBusinessBundle\Http\GraphApiClient;
use Nowo\WhatsappBusinessBundle\Http\GraphApiException;
use Nowo\WhatsappBusinessBundle\Message\MediaDownload;
use Nowo\WhatsappBusinessBundle\Message\MediaId;
use Nowo\WhatsappBusinessBundle\Message\MediaSource;
use Nowo\WhatsappBusinessBundle\Message\TextMessage;
use Nowo\WhatsappBusinessBundle\Tests\Unit\Support\ProfileFactory;
use Nowo\WhatsappBusinessBundle\Transport\CloudApiTransport;
use Nowo\WhatsappBusinessBundle\Transport\MockTransport;
use Nowo\WhatsappBusinessBundle\Transport\TransportInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(WhatsappClient::class)]
#[CoversClass(MessageSentEvent::class)]
#[CoversClass(MessageSendFailedEvent::class)]
final class WhatsappClientTest extends TestCase
{
    public function testSendTextAndRawAndMediaHelpers(): void
    {
        $transport = new MockTransport(ProfileFactory::mock());
        $client    = new WhatsappClient(ProfileFactory::mock(), $transport, new EventDispatcher());

        $sent = $client->sendText('34611111111', 'hello', true);
        self::assertStringStartsWith('mock.', $sent->messageId);
        self::assertSame('default', $client->profile()->name);

        $template = $client->sendTemplate('34611111111', 'hello_world', 'en_US', ['Ada']);
        self::assertNotSame('', $template->messageId);

        $image = $client->sendImage('34611111111', MediaSource::id('MEDIA'));
        self::assertNotSame('', $image->messageId);

        $raw = $client->sendRaw('34611111111', ['type' => 'text', 'text' => ['body' => 'raw']]);
        self::assertNotSame('', $raw->messageId);

        $client->markAsRead('wamid.1');
        $media = $client->uploadMedia('/tmp/x', 'image/jpeg');
        self::assertStringStartsWith('mock-media.', $media->id);
        self::assertSame('mock-bytes', $client->downloadMedia('m1')->contents);
        self::assertCount(1, $client->listTemplates());
        self::assertNotSame([], $transport->recorded());
        $transport->reset();
        self::assertSame([], $transport->recorded());
    }

    public function testSendDispatchesFailure(): void
    {
        $transport = new class implements TransportInterface {
            public function send(array $graphBody): array
            {
                throw GraphApiException::fromGraph(['error' => ['message' => 'nope', 'code' => 100]], 400);
            }

            public function markAsRead(string $messageId): void
            {
            }

            public function uploadMedia(string $filePath, string $mimeType): MediaId
            {
                return new MediaId('x', []);
            }

            public function downloadMedia(string $mediaId): MediaDownload
            {
                return new MediaDownload('', null, null);
            }

            public function listTemplates(): array
            {
                return [];
            }
        };
        $dispatcher = new EventDispatcher();
        $failed     = 0;
        $dispatcher->addListener(MessageSendFailedEvent::class, static function () use (&$failed): void {
            ++$failed;
        });
        $dispatcher->addListener(MessageSentEvent::class, static function (): never {
            self::fail('sent event must not fire');
        });
        $client = new WhatsappClient(ProfileFactory::mock(), $transport, $dispatcher);

        $this->expectException(GraphApiException::class);
        try {
            $client->sendMessage('34611111111', new TextMessage('x'));
        } finally {
            self::assertSame(1, $failed);
        }
    }

    public function testNonJsonGraphResponseDispatchesSendFailedEvent(): void
    {
        $http      = new MockHttpClient([new MockResponse('<html>502 Bad Gateway</html>', ['http_code' => 502])]);
        $profile   = ProfileFactory::cloud();
        $transport = new CloudApiTransport(new GraphApiClient($http, $profile), $profile);

        $dispatcher = new EventDispatcher();
        $failures   = [];
        $dispatcher->addListener(MessageSendFailedEvent::class, static function (MessageSendFailedEvent $event) use (&$failures): void {
            $failures[] = $event;
        });
        $client = new WhatsappClient($profile, $transport, $dispatcher);

        try {
            $client->sendMessage('34611111111', new TextMessage('x'));
            self::fail('GraphApiException expected');
        } catch (GraphApiException $exception) {
            self::assertSame(502, $exception->httpStatus);
        }
        self::assertCount(1, $failures);
    }
}
