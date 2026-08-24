<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Message;

use Nowo\WhatsappBusinessBundle\Message\MediaDownload;
use Nowo\WhatsappBusinessBundle\Message\MediaId;
use Nowo\WhatsappBusinessBundle\Message\MessageResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageResult::class)]
#[CoversClass(MediaId::class)]
#[CoversClass(MediaDownload::class)]
final class MessageResultTest extends TestCase
{
    public function testFromGraph(): void
    {
        $result = MessageResult::fromGraph([
            'messages' => [['id' => 'wamid.1']],
            'contacts' => [['wa_id' => '34611']],
        ]);
        self::assertSame('wamid.1', $result->messageId);
        self::assertSame(['34611'], $result->recipientIds);
    }

    public function testFromGraphEmpty(): void
    {
        $result = MessageResult::fromGraph(['messages' => 'nope', 'contacts' => 'nope']);
        self::assertSame('', $result->messageId);
        self::assertSame([], $result->recipientIds);
    }

    public function testMediaValueObjects(): void
    {
        $id = new MediaId('m1', ['id' => 'm1']);
        self::assertSame('m1', $id->id);
        $download = new MediaDownload('bytes', 'image/jpeg', 'https://example.com/m');
        self::assertSame('bytes', $download->contents);
    }
}
