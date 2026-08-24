<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Message;

use Nowo\WhatsappBusinessBundle\Exception\InvalidPhoneNumberException;
use Nowo\WhatsappBusinessBundle\Message\PhoneNumber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhoneNumber::class)]
final class PhoneNumberTest extends TestCase
{
    public function testParseStripsPlusAndSeparators(): void
    {
        $phone = PhoneNumber::parse('+34 657-08.54(10)');
        self::assertSame('34657085410', $phone->digits);
        self::assertSame('*******5410', $phone->masked());
    }

    public function testParseStripsInternationalPrefix(): void
    {
        self::assertSame('34657085410', PhoneNumber::parse('0034657085410')->digits);
    }

    public function testMaskedShortNumber(): void
    {
        self::assertSame('****5678', PhoneNumber::parse('12345678')->masked());
    }

    #[DataProvider('invalidProvider')]
    public function testInvalid(string $value): void
    {
        $this->expectException(InvalidPhoneNumberException::class);
        PhoneNumber::parse($value);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'letters' => ['abc'];
        yield 'too-short' => ['1234567'];
        yield 'too-long' => ['1234567890123456'];
        yield 'empty' => [''];
    }
}
