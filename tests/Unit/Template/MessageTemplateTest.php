<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Template;

use Nowo\WhatsappBusinessBundle\Template\MessageTemplate;
use Nowo\WhatsappBusinessBundle\Template\TemplateStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageTemplate::class)]
#[CoversClass(TemplateStatus::class)]
final class MessageTemplateTest extends TestCase
{
    public function testFromGraphAndUnknownStatus(): void
    {
        $template = MessageTemplate::fromGraph([
            'id'       => '1',
            'name'     => 'hello_world',
            'status'   => 'APPROVED',
            'language' => 'en_US',
            'category' => 'UTILITY',
        ]);
        self::assertSame(TemplateStatus::Approved, $template->status);
        $empty = MessageTemplate::fromGraph([]);
        self::assertSame(TemplateStatus::Unknown, $empty->status);
        self::assertSame(TemplateStatus::Unknown, TemplateStatus::fromGraph('WAT'));
        self::assertSame(TemplateStatus::Pending, TemplateStatus::fromGraph('pending'));
    }
}
