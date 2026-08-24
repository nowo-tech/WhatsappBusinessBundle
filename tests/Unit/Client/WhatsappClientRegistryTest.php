<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Client;

use Nowo\WhatsappBusinessBundle\Client\WhatsappClient;
use Nowo\WhatsappBusinessBundle\Client\WhatsappClientRegistry;
use Nowo\WhatsappBusinessBundle\Exception\UnknownProfileException;
use Nowo\WhatsappBusinessBundle\Tests\Unit\Support\ProfileFactory;
use Nowo\WhatsappBusinessBundle\Transport\MockTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WhatsappClientRegistry::class)]
final class WhatsappClientRegistryTest extends TestCase
{
    public function testGetHasAndNames(): void
    {
        $client   = new WhatsappClient(ProfileFactory::mock(), new MockTransport(ProfileFactory::mock()));
        $registry = new WhatsappClientRegistry(['default' => $client], 'default');
        self::assertSame($client, $registry->get());
        self::assertTrue($registry->has('default'));
        self::assertFalse($registry->has('missing'));
        self::assertSame(['default'], $registry->names());
    }

    public function testUnknownOnConstruct(): void
    {
        $this->expectException(UnknownProfileException::class);
        new WhatsappClientRegistry([], 'default');
    }

    public function testUnknownOnGet(): void
    {
        $client   = new WhatsappClient(ProfileFactory::mock(), new MockTransport(ProfileFactory::mock()));
        $registry = new WhatsappClientRegistry(['default' => $client], 'default');
        $this->expectException(UnknownProfileException::class);
        $registry->get('other');
    }
}
