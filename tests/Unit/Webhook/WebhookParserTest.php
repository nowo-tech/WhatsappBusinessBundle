<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Webhook;

use Nowo\WhatsappBusinessBundle\Webhook\IncomingMessage;
use Nowo\WhatsappBusinessBundle\Webhook\StatusUpdate;
use Nowo\WhatsappBusinessBundle\Webhook\WebhookNotification;
use Nowo\WhatsappBusinessBundle\Webhook\WebhookParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhookParser::class)]
#[CoversClass(IncomingMessage::class)]
#[CoversClass(StatusUpdate::class)]
#[CoversClass(WebhookNotification::class)]
final class WebhookParserTest extends TestCase
{
    public function testParseMessagesAndStatuses(): void
    {
        $notification = (new WebhookParser())->parse([
            'object' => 'whatsapp_business_account',
            'entry'  => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'id'        => 'wamid.1',
                            'from'      => '34611',
                            'type'      => 'text',
                            'timestamp' => '1',
                            'text'      => ['body' => 'hi'],
                        ]],
                        'statuses' => [[
                            'id'           => 'wamid.2',
                            'status'       => 'delivered',
                            'timestamp'    => '2',
                            'recipient_id' => '34611',
                        ]],
                    ],
                ]],
            ]],
        ]);
        self::assertSame('whatsapp_business_account', $notification->object);
        self::assertSame('hi', $notification->messages[0]->text);
        self::assertSame('delivered', $notification->statuses[0]->status);
    }

    public function testParseIgnoresMalformedTrees(): void
    {
        $parser = new WebhookParser();
        self::assertSame([], $parser->parse(['entry' => 'nope'])->messages);
        self::assertSame([], $parser->parse(['entry' => ['x']])->messages);
        self::assertSame([], $parser->parse(['entry' => [['changes' => 'x']]])->messages);
        self::assertSame([], $parser->parse(['entry' => [['changes' => ['x']]]])->messages);
        self::assertSame([], $parser->parse(['entry' => [['changes' => [['value' => 'x']]]]])->messages);
        self::assertSame([], $parser->parse(['entry' => [['changes' => [['value' => ['messages' => 'x', 'statuses' => 'y']]]]]])->messages);
        $emptyItems = $parser->parse(['entry' => [['changes' => [['value' => ['messages' => [1], 'statuses' => [1]]]]]]]);
        self::assertSame([], $emptyItems->messages);
        self::assertSame([], $emptyItems->statuses);
        $bare = IncomingMessage::fromGraph([]);
        self::assertSame('', $bare->id);
        $status = StatusUpdate::fromGraph([]);
        self::assertNull($status->recipientId);
    }
}
