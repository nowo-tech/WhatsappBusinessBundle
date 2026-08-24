<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Messenger;

use Nowo\WhatsappBusinessBundle\Client\WhatsappClient;
use Nowo\WhatsappBusinessBundle\Client\WhatsappClientRegistry;
use Nowo\WhatsappBusinessBundle\Message\OutgoingEnvelope;
use Nowo\WhatsappBusinessBundle\Message\PhoneNumber;
use Nowo\WhatsappBusinessBundle\Message\TextMessage;
use Nowo\WhatsappBusinessBundle\Messenger\SendOutgoingMessage;
use Nowo\WhatsappBusinessBundle\Messenger\SendOutgoingMessageHandler;
use Nowo\WhatsappBusinessBundle\Tests\Unit\Support\ProfileFactory;
use Nowo\WhatsappBusinessBundle\Transport\MockTransport;
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
}
