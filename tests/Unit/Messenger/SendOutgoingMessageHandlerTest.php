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
use PHPUnit\Framework\TestCase;

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

    public function testUnknownProfileIsRethrownForMessengerFailurePolicy(): void
    {
        $client  = new WhatsappClient(ProfileFactory::mock(), new MockTransport(ProfileFactory::mock()));
        $handler = new SendOutgoingMessageHandler(new WhatsappClientRegistry(['default' => $client], 'default'));

        $this->expectException(UnknownProfileException::class);
        $handler(new SendOutgoingMessage(
            new OutgoingEnvelope(PhoneNumber::parse('34611111111'), new TextMessage('hi')),
            'missing',
        ));
    }

    public function testGraphFailureIsRethrownSoMessengerCanRetry(): void
    {
        $failing = new class implements TransportInterface {
            public function send(array $graphBody): array
            {
                throw GraphApiException::fromGraph(['error' => ['message' => 'Rate limit hit', 'code' => 130429]], 429);
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
            self::fail('GraphApiException expected.');
        } catch (GraphApiException $exception) {
            self::assertSame(429, $exception->httpStatus);
            self::assertSame(130429, $exception->graphCode);
        }
    }
}
