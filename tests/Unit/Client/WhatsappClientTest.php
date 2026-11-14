<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Client;

use ArrayObject;
use Nowo\WhatsappBusinessBundle\Client\WhatsappClient;
use Nowo\WhatsappBusinessBundle\Config\CloudApiProfile;
use Nowo\WhatsappBusinessBundle\Event\MessageSendFailedEvent;
use Nowo\WhatsappBusinessBundle\Event\MessageSentEvent;
use Nowo\WhatsappBusinessBundle\Http\GraphApiClient;
use Nowo\WhatsappBusinessBundle\Http\GraphApiException;
use Nowo\WhatsappBusinessBundle\Message\MediaDownload;
use Nowo\WhatsappBusinessBundle\Message\MediaId;
use Nowo\WhatsappBusinessBundle\Message\MediaSource;
use Nowo\WhatsappBusinessBundle\Message\TextMessage;
use Nowo\WhatsappBusinessBundle\Tests\Support\ProfileAwareHostTransport;
use Nowo\WhatsappBusinessBundle\Tests\Support\RecordingLogger;
use Nowo\WhatsappBusinessBundle\Tests\Support\ScriptedHostTransport;
use Nowo\WhatsappBusinessBundle\Tests\Unit\Support\ProfileFactory;
use Nowo\WhatsappBusinessBundle\Transport\CloudApiTransport;
use Nowo\WhatsappBusinessBundle\Transport\MockTransport;
use Nowo\WhatsappBusinessBundle\Transport\TransportInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

use function json_encode;

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

    /**
     * L-2 (audit PH): any Throwable from a host transport fires the failure event and the error log exactly like a
     * GraphApiException, is rethrown as a retryable GraphApiException (status 0) and never leaks the token.
     */
    public function testHostTransportThrowableIsReportedLoggedAndWrapped(): void
    {
        $token                               = 'EAAGsecret-token-1234567890';
        $original                            = new RuntimeException('gateway down while using ' . $token);
        $transport                           = new ScriptedHostTransport(static fn (array $body): never => throw $original);
        [$client, $failures, $sent, $logger] = $this->observedClient(ProfileFactory::custom('gateway', $token), $transport);

        try {
            $client->sendMessage('34611111111', new TextMessage('x'));
            self::fail('GraphApiException expected.');
        } catch (GraphApiException $exception) {
            self::assertSame(0, $exception->httpStatus);
            self::assertSame($original, $exception->getPrevious());
            self::assertFalse($exception->isPermanent());
            self::assertStringNotContainsString($token, $exception->getMessage());
            [$failure] = $failures->getArrayCopy();
            self::assertCount(1, $failures);
            self::assertSame($exception, $failure->exception);
            self::assertSame('gateway', $failure->profile);
        }
        self::assertCount(0, $sent);
        self::assertSame([['error', 'WhatsApp send failed.']], $logger->levelsAndMessages());
        self::assertSame('gateway', $logger->records[0]['context']['profile']);
        self::assertSame(0, $logger->records[0]['context']['http']);
        self::assertStringNotContainsString($token, (string) json_encode($logger->records));
    }

    public function testHostTransportUnrecoverableThrowableIsPermanent(): void
    {
        $transport           = new ScriptedHostTransport(static fn (array $body): never => throw new UnrecoverableMessageHandlingException('recipient blocked'));
        [$client, $failures] = $this->observedClient(ProfileFactory::custom(), $transport);

        try {
            $client->sendMessage('34611111111', new TextMessage('x'));
            self::fail('GraphApiException expected.');
        } catch (GraphApiException $exception) {
            self::assertTrue($exception->isPermanent());
            self::assertInstanceOf(UnrecoverableMessageHandlingException::class, $exception->getPrevious());
        }
        self::assertCount(1, $failures);
    }

    public function testHostTransportGraphApiExceptionIsPassedThroughUnwrapped(): void
    {
        $graph               = GraphApiException::fromGraph(['error' => ['message' => 'bad recipient', 'code' => 131030]], 400);
        $transport           = new ScriptedHostTransport(static fn (array $body): never => throw $graph);
        [$client, $failures] = $this->observedClient(ProfileFactory::custom(), $transport);

        try {
            $client->sendMessage('34611111111', new TextMessage('x'));
            self::fail('GraphApiException expected.');
        } catch (GraphApiException $exception) {
            self::assertSame($graph, $exception);
        }
        self::assertCount(1, $failures);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function responsesWithoutMessageId(): iterable
    {
        yield 'empty array' => [[]];
        yield 'no messages key' => [['messaging_product' => 'whatsapp']];
        yield 'empty messages' => [['messages' => []]];
        yield 'empty id' => [['messages' => [['id' => '']]]];
        yield 'non-string id' => [['messages' => [['id' => 42]]]];
    }

    /**
     * L-3 (audit PH): a host response without `messages[0].id` is a failed send, not a delivered message.
     *
     * @param array<string, mixed> $response
     */
    #[DataProvider('responsesWithoutMessageId')]
    public function testHostResponseWithoutMessageIdIsAFailure(array $response): void
    {
        $transport                           = new ScriptedHostTransport(static fn (array $body): array => $response);
        [$client, $failures, $sent, $logger] = $this->observedClient(ProfileFactory::custom(), $transport);

        try {
            $client->sendMessage('34611111111', new TextMessage('x'));
            self::fail('GraphApiException expected.');
        } catch (GraphApiException $exception) {
            self::assertSame(0, $exception->httpStatus);
            self::assertTrue($exception->isPermanent());
            self::assertCount(1, $failures);
        }
        self::assertCount(0, $sent);
        self::assertSame([['error', 'WhatsApp send failed.']], $logger->levelsAndMessages());
    }

    /**
     * The Cloud transport keeps its 1.3 behaviour: the L-2 / L-3 guards apply to host transports only.
     */
    public function testCloudResponseHandlingIsUnchanged(): void
    {
        $http                       = new MockHttpClient([new MockResponse('{"messages":[{"id":"wamid.1"}],"contacts":[{"wa_id":"34611111111"}]}', ['http_code' => 200])]);
        $profile                    = ProfileFactory::cloud();
        $transport                  = new CloudApiTransport(new GraphApiClient($http, $profile), $profile);
        [$client, $failures, $sent] = $this->observedClient($profile, $transport);

        self::assertSame('wamid.1', $client->sendText('34611111111', 'hi')->messageId);
        self::assertCount(0, $failures);
        self::assertCount(1, $sent);
    }

    /**
     * I-1 (audit PH): one host service shared by several profiles receives the profile name once, at client
     * construction, and every profile sends through its own bound transport.
     */
    public function testProfileAwareTransportIsBoundToTheClientProfile(): void
    {
        $shared = new ProfileAwareHostTransport();
        $sales  = new WhatsappClient(ProfileFactory::custom('sales'), $shared);
        $alerts = new WhatsappClient(ProfileFactory::custom('alerts'), $shared);

        self::assertSame('sales.sent', $sales->sendText('34611111111', 'a')->messageId);
        self::assertSame('alerts.sent', $alerts->sendText('34611111111', 'b')->messageId);
        self::assertSame('sales.media', $sales->uploadMedia('/dev/null', 'image/jpeg')->id);
    }

    /**
     * @return array{WhatsappClient, ArrayObject<int, MessageSendFailedEvent>, ArrayObject<int, MessageSentEvent>, RecordingLogger}
     */
    private function observedClient(CloudApiProfile $profile, TransportInterface $transport): array
    {
        /** @var ArrayObject<int, MessageSendFailedEvent> $failures */
        $failures = new ArrayObject();
        /** @var ArrayObject<int, MessageSentEvent> $sent */
        $sent       = new ArrayObject();
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(MessageSendFailedEvent::class, static function (MessageSendFailedEvent $event) use ($failures): void {
            $failures->append($event);
        });
        $dispatcher->addListener(MessageSentEvent::class, static function (MessageSentEvent $event) use ($sent): void {
            $sent->append($event);
        });
        $logger = new RecordingLogger();

        return [new WhatsappClient($profile, $transport, $dispatcher, $logger), $failures, $sent, $logger];
    }
}
