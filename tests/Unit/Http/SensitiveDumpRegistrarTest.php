<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Http;

use Nowo\WhatsappBusinessBundle\Http\SecretRedactor;
use Nowo\WhatsappBusinessBundle\Http\SensitiveDumpRegistrar;
use Nowo\WhatsappBusinessBundle\Tests\Unit\Support\ProfileFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(SensitiveDumpRegistrar::class)]
final class SensitiveDumpRegistrarTest extends TestCase
{
    public function testRegisterIsIdempotentAndCastsDebugInfo(): void
    {
        SensitiveDumpRegistrar::register();
        SensitiveDumpRegistrar::register();

        $casted = SensitiveDumpRegistrar::castUsingDebugInfo(ProfileFactory::cloud(), ['accessToken' => 'leaked']);
        self::assertSame(SecretRedactor::PLACEHOLDER, $casted['accessToken']);
        self::assertSame(['keep' => 1], SensitiveDumpRegistrar::castUsingDebugInfo(new stdClass(), ['keep' => 1]));
    }
}
