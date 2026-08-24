<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Command;

use Nowo\WhatsappBusinessBundle\Client\WhatsappClient;
use Nowo\WhatsappBusinessBundle\Client\WhatsappClientRegistry;
use Nowo\WhatsappBusinessBundle\Command\ListTemplatesCommand;
use Nowo\WhatsappBusinessBundle\Command\SendTextCommand;
use Nowo\WhatsappBusinessBundle\Tests\Unit\Support\ProfileFactory;
use Nowo\WhatsappBusinessBundle\Transport\MockTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(SendTextCommand::class)]
#[CoversClass(ListTemplatesCommand::class)]
final class CommandsTest extends TestCase
{
    public function testSendText(): void
    {
        $client   = new WhatsappClient(ProfileFactory::mock(), new MockTransport(ProfileFactory::mock()));
        $registry = new WhatsappClientRegistry(['default' => $client], 'default');
        $tester   = new CommandTester(new SendTextCommand($registry));
        $tester->execute(['to' => '34611111111', 'body' => 'hello', '--profile' => 'default']);
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('mock.', $tester->getDisplay());
    }

    public function testSendTextDefaultProfile(): void
    {
        $client = new WhatsappClient(ProfileFactory::mock(), new MockTransport(ProfileFactory::mock()));
        $tester = new CommandTester(new SendTextCommand(new WhatsappClientRegistry(['default' => $client], 'default')));
        $tester->execute(['to' => '34611111111', 'body' => 'hello']);
        self::assertSame(0, $tester->getStatusCode());
    }

    public function testListTemplates(): void
    {
        $client = new WhatsappClient(ProfileFactory::mock(), new MockTransport(ProfileFactory::mock()));
        $tester = new CommandTester(new ListTemplatesCommand(new WhatsappClientRegistry(['default' => $client], 'default')));
        $tester->execute(['--profile' => '']);
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('hello_world', $tester->getDisplay());
    }
}
