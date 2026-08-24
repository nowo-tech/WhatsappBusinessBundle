<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Message;

use InvalidArgumentException;
use Nowo\WhatsappBusinessBundle\Message\GraphPayloadMessage;
use Nowo\WhatsappBusinessBundle\Message\LocationMessage;
use Nowo\WhatsappBusinessBundle\Message\MediaMessage;
use Nowo\WhatsappBusinessBundle\Message\MediaSource;
use Nowo\WhatsappBusinessBundle\Message\MessageType;
use Nowo\WhatsappBusinessBundle\Message\OutgoingEnvelope;
use Nowo\WhatsappBusinessBundle\Message\PhoneNumber;
use Nowo\WhatsappBusinessBundle\Message\RawGraphMessage;
use Nowo\WhatsappBusinessBundle\Message\ReactionMessage;
use Nowo\WhatsappBusinessBundle\Message\TemplateMessage;
use Nowo\WhatsappBusinessBundle\Message\TextMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextMessage::class)]
#[CoversClass(TemplateMessage::class)]
#[CoversClass(MediaMessage::class)]
#[CoversClass(MediaSource::class)]
#[CoversClass(LocationMessage::class)]
#[CoversClass(ReactionMessage::class)]
#[CoversClass(GraphPayloadMessage::class)]
#[CoversClass(RawGraphMessage::class)]
#[CoversClass(OutgoingEnvelope::class)]
final class OutgoingMessagesTest extends TestCase
{
    public function testTextAndEnvelopeExtras(): void
    {
        $envelope = new OutgoingEnvelope(
            PhoneNumber::parse('34611111111'),
            new TextMessage('hello', true),
            'wamid.reply',
            'opaque',
        );
        $body = $envelope->toGraphBody();
        self::assertSame('whatsapp', $body['messaging_product']);
        self::assertSame('text', $body['type']);
        self::assertTrue($body['text']['preview_url']);
        self::assertSame('wamid.reply', $body['context']['message_id']);
        self::assertSame('opaque', $body['biz_opaque_callback_data']);
    }

    public function testEnvelopeOmitsEmptyExtras(): void
    {
        $body = (new OutgoingEnvelope(PhoneNumber::parse('34611111111'), new TextMessage('x'), '', ''))->toGraphBody();
        self::assertArrayNotHasKey('context', $body);
        self::assertArrayNotHasKey('biz_opaque_callback_data', $body);
    }

    public function testTemplateWithParametersAndHeaderMedia(): void
    {
        $withMedia = new TemplateMessage('hello_world', 'en_US', ['Juan'], [], [], MediaSource::id('MEDIA1'));
        self::assertSame('template', $withMedia->type()->value);
        self::assertSame('image', $withMedia->graphFields()['template']['components'][0]['parameters'][0]['type']);

        $withHeaderText = new TemplateMessage('hello_world', 'en_US', ['a'], ['hdr'], ['btn']);
        $fields         = $withHeaderText->graphFields();
        self::assertCount(3, $fields['template']['components']);
    }

    public function testTemplateWithoutComponents(): void
    {
        $fields = (new TemplateMessage('hello_world', 'en_US'))->graphFields();
        self::assertArrayNotHasKey('components', $fields['template']);
    }

    public function testMediaSourceAndMessages(): void
    {
        $link = MediaSource::link('https://example.com/a.jpg');
        self::assertSame(['link' => 'https://example.com/a.jpg'], $link->toGraphField());
        self::assertSame(['id' => 'abc'], MediaSource::id('abc')->toGraphField());

        $image = MediaMessage::image($link, 'cap');
        self::assertSame('image', $image->type()->value);
        self::assertSame('cap', $image->graphFields()['image']['caption']);

        $doc = MediaMessage::document(MediaSource::id('d1'), 'c', 'file.pdf');
        self::assertSame('file.pdf', $doc->graphFields()['document']['filename']);

        self::assertSame(MessageType::Audio, MediaMessage::audio(MediaSource::id('a'))->type());
        self::assertSame(MessageType::Video, MediaMessage::video(MediaSource::id('v'), 'hi')->type());
        self::assertSame(MessageType::Sticker, MediaMessage::sticker(MediaSource::id('s'))->type());
    }

    public function testMediaSourceRejectsInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MediaSource::id('');
    }

    public function testMediaSourceRejectsHttp(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MediaSource::link('http://example.com/a.jpg');
    }

    public function testMediaMessageRejectsWrongType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MediaMessage(MessageType::Text, MediaSource::id('x'));
    }

    public function testLocationReactionAndGraphPayload(): void
    {
        $location = new LocationMessage(1.5, 2.5, 'HQ', 'Street 1');
        self::assertSame('HQ', $location->graphFields()['location']['name']);
        $bare = new LocationMessage(1.0, 2.0);
        self::assertArrayNotHasKey('name', $bare->graphFields()['location']);

        $reaction = new ReactionMessage('wamid.1', '👍');
        self::assertSame('reaction', $reaction->type()->value);

        $contacts = GraphPayloadMessage::contacts([['name' => ['formatted_name' => 'A']]]);
        self::assertSame(MessageType::Contacts, $contacts->type());
        self::assertArrayHasKey('contacts', $contacts->graphFields());
        $interactive = GraphPayloadMessage::interactive(['type' => 'button']);
        self::assertSame(MessageType::Interactive, $interactive->type());
        self::assertSame(['interactive' => ['type' => 'button']], $interactive->graphFields());

        $withEmptyName = new LocationMessage(1.0, 2.0, '', 'Street 1');
        self::assertArrayNotHasKey('name', $withEmptyName->graphFields()['location']);
        self::assertSame('Street 1', $withEmptyName->graphFields()['location']['address']);
        self::assertSame(MessageType::Location, $location->type());

        $reaction = new ReactionMessage('wamid.1', '👍');
        self::assertSame('reaction', $reaction->type()->value);
        self::assertSame('wamid.1', $reaction->graphFields()['reaction']['message_id']);
    }

    public function testRawGraphMessageStripsEnvelopeKeys(): void
    {
        $raw = RawGraphMessage::fromGraphBody([
            'messaging_product' => 'whatsapp',
            'to'                => '1',
            'type'              => 'text',
            'text'              => ['body' => 'hi'],
        ]);
        self::assertSame(MessageType::Text, $raw->type());
        self::assertSame(['text' => ['body' => 'hi']], $raw->graphFields());

        $unknown = RawGraphMessage::fromGraphBody(['type' => 'not-a-type', 'foo' => 1]);
        self::assertSame(MessageType::Text, $unknown->type());
    }
}
