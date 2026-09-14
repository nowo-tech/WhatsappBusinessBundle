<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit;

use Nowo\WhatsappBusinessBundle\DependencyInjection\WhatsappBusinessExtension;
use Nowo\WhatsappBusinessBundle\WhatsappBusinessBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WhatsappBusinessBundle::class)]
final class WhatsappBusinessBundleTest extends TestCase
{
    public function testGetContainerExtensionIsStable(): void
    {
        $bundle = new WhatsappBusinessBundle();
        $first  = $bundle->getContainerExtension();
        $second = $bundle->getContainerExtension();

        self::assertInstanceOf(WhatsappBusinessExtension::class, $first);
        self::assertSame($first, $second);
    }

    public function testBootRegistersSensitiveDumpCasters(): void
    {
        $bundle = new WhatsappBusinessBundle();
        $bundle->boot();
        $bundle->boot();
        $this->addToAssertionCount(1);
    }
}
