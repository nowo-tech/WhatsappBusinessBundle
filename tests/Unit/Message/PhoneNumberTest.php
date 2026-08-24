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
        $phone = PhoneNumber::parse('+34 600-11.22(33)');
        self::assertSame('34600112233', $phone->digits);
        self::assertSame('*******2233', $phone->masked());
    }

    public function testParseStripsInternationalPrefix(): void
    {
        self::assertSame('34600112233', PhoneNumber::parse('0034600112233')->digits);
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
