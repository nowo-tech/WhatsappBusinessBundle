<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Exception;

use Nowo\WhatsappBusinessBundle\Exception\InvalidPhoneNumberException;
use Nowo\WhatsappBusinessBundle\Exception\MissingBusinessAccountException;
use Nowo\WhatsappBusinessBundle\Exception\UnknownProfileException;
use Nowo\WhatsappBusinessBundle\Exception\WebhookVerificationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnknownProfileException::class)]
#[CoversClass(InvalidPhoneNumberException::class)]
#[CoversClass(MissingBusinessAccountException::class)]
#[CoversClass(WebhookVerificationException::class)]
final class ExceptionFactoriesTest extends TestCase
{
    public function testFactories(): void
    {
        self::assertStringContainsString('prod', UnknownProfileException::forProfile('prod')->getMessage());
        self::assertStringContainsString('digits', InvalidPhoneNumberException::invalid()->getMessage());
        self::assertStringContainsString('prod', MissingBusinessAccountException::forProfile('prod')->getMessage());
        self::assertStringContainsString('signature', WebhookVerificationException::invalidSignature()->getMessage());
        self::assertStringContainsString('Missing', WebhookVerificationException::missingSignature()->getMessage());
        self::assertStringContainsString('verify', WebhookVerificationException::invalidVerifyToken()->getMessage());
    }
}
