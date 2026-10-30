<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Messenger;

use Nowo\WhatsappBusinessBundle\Client\WhatsappClient;
use Nowo\WhatsappBusinessBundle\Client\WhatsappClientRegistry;
use Nowo\WhatsappBusinessBundle\Exception\UnknownProfileException;
use Nowo\WhatsappBusinessBundle\Http\GraphApiException;
use Nowo\WhatsappBusinessBundle\Message\MediaDownload;
use Nowo\WhatsappBusinessBundle\Message\MediaId;
use Nowo\WhatsappBusinessBundle\Message\OutgoingEnvelope;
use Nowo\WhatsappBusinessBundle\Message\PhoneNumber;
use Nowo\WhatsappBusinessBundle\Message\TextMessage;
use Nowo\WhatsappBusinessBundle\Messenger\SendOutgoingMessage;
use Nowo\WhatsappBusinessBundle\Messenger\SendOutgoingMessageHandler;
use Nowo\WhatsappBusinessBundle\Tests\Unit\Support\ProfileFactory;
use Nowo\WhatsappBusinessBundle\Transport\MockTransport;
use Nowo\WhatsappBusinessBundle\Transport\TransportInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[CoversClass(SendOutgoingMessageHandler::class)]
#[CoversClass(SendOutgoingMessage::class)]
final class SendOutgoingMessageHandlerTest extends TestCase
{
    public function testInvoke(): void
    {
        $client  = new WhatsappClient(ProfileFactory::mock(), new MockTransport(ProfileFactory::mock()));
        $handler = new SendOutgoingMessageHandler(new WhatsappClientRegistry(['default' => $client], 'default'));
        $result  = $handler(new SendOutgoingMessage(
            new OutgoingEnvelope(PhoneNumber::parse('34611111111'), new TextMessage('hi')),
        ));
        self::assertStringStartsWith('mock.', $result->messageId);
    }

    public function testUnknownProfileIsUnrecoverable(): void
    {
        $client  = new WhatsappClient(ProfileFactory::mock(), new MockTransport(ProfileFactory::mock()));
        $handler = new SendOutgoingMessageHandler(new WhatsappClientRegistry(['default' => $client], 'default'));

        try {
            $handler(new SendOutgoingMessage(
                new OutgoingEnvelope(PhoneNumber::parse('34611111111'), new TextMessage('hi')),
                'missing',
            ));
            self::fail('UnrecoverableMessageHandlingException expected.');
        } catch (UnrecoverableMessageHandlingException $exception) {
            self::assertInstanceOf(UnknownProfileException::class, $exception->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function graphFailures(): iterable
    {
        yield 'rate limit 429 is retried' => [429, false];
        yield 'server error 503 is retried' => [503, false];
        yield 'transport failure is retried' => [0, false];
        yield 'invalid recipient 400 is permanent' => [400, true];
        yield 'not found 404 is permanent' => [404, true];
    }

    #[DataProvider('graphFailures')]
    public function testGraphFailuresAreRetriedOnlyWhenTransient(int $status, bool $unrecoverable): void
    {
        $failing = new class($status) implements TransportInterface {
            public function __construct(private readonly int $status)
            {
            }

            public function send(array $graphBody): array
            {
                throw GraphApiException::fromGraph(['error' => ['message' => 'Graph error', 'code' => 131030]], $this->status);
            }

            public function markAsRead(string $messageId): void
            {
            }

            public function uploadMedia(string $filePath, string $mimeType): MediaId
            {
                return new MediaId('unused', []);
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
        $client  = new WhatsappClient(ProfileFactory::mock(), $failing);
        $handler = new SendOutgoingMessageHandler(new WhatsappClientRegistry(['default' => $client], 'default'));

        try {
            $handler(new SendOutgoingMessage(
                new OutgoingEnvelope(PhoneNumber::parse('34611111111'), new TextMessage('hi')),
            ));
            self::fail('Exception expected.');
        } catch (UnrecoverableMessageHandlingException $exception) {
            self::assertTrue($unrecoverable);
            self::assertInstanceOf(GraphApiException::class, $exception->getPrevious());
            self::assertSame($status, $exception->getCode());
        } catch (GraphApiException $exception) {
            self::assertFalse($unrecoverable);
            self::assertSame($status, $exception->httpStatus);
        }
    }
}
