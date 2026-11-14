<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Http;

use Nowo\WhatsappBusinessBundle\Http\GraphApiException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[CoversClass(GraphApiException::class)]
final class GraphApiExceptionTest extends TestCase
{
    public function testFromGraphWithErrorObject(): void
    {
        $e = GraphApiException::fromGraph([
            'error' => [
                'message'       => 'fail',
                'code'          => 100,
                'error_subcode' => 33,
                'fbtrace_id'    => 'trace',
            ],
        ], 400);
        self::assertSame('fail', $e->getMessage());
        self::assertSame(400, $e->httpStatus);
        self::assertSame(100, $e->graphCode);
        self::assertSame(33, $e->subcode);
        self::assertSame('trace', $e->fbtraceId);
        $info = $e->__debugInfo();
        self::assertSame('[omitted]', $info['raw']);
        self::assertSame('fail', $info['message']);
    }

    public function testRedactsTokensInMessageAndRaw(): void
    {
        $e = GraphApiException::fromGraph([
            'error'        => ['message' => 'Invalid token EAAG12345678zzzz'],
            'access_token' => 'should-not-leak',
        ], 401);
        self::assertSame('Invalid token [redacted]', $e->getMessage());
        self::assertSame('[redacted]', $e->raw['access_token']);
    }

    public function testFactoryHelpers(): void
    {
        self::assertSame('WhatsApp Cloud API transport failure.', GraphApiException::transportFailure()->getMessage());
        self::assertSame('WhatsApp media download URL is not allowed.', GraphApiException::mediaUrlRejected()->getMessage());
    }

    public function testFromGraphWithoutError(): void
    {
        $e = GraphApiException::fromGraph([], 500);
        self::assertSame('WhatsApp Cloud API request failed.', $e->getMessage());
        self::assertNull($e->graphCode);
    }

    public function testPermanenceFollowsTheHttpStatus(): void
    {
        self::assertTrue(GraphApiException::fromGraph([], 400)->isPermanent());
        self::assertTrue(GraphApiException::fromGraph([], 499)->isPermanent());
        self::assertFalse(GraphApiException::fromGraph([], 429)->isPermanent());
        self::assertFalse(GraphApiException::fromGraph([], 500)->isPermanent());
        self::assertFalse(GraphApiException::transportFailure()->isPermanent());
        self::assertFalse(GraphApiException::fromGraph([], 399)->isPermanent());
    }

    public function testHostTransportFailureKeepsThePreviousButNotItsMessage(): void
    {
        $previous = new RuntimeException('token EAAG-leak-me');
        $e        = GraphApiException::hostTransportFailure($previous);
        self::assertSame(0, $e->httpStatus);
        self::assertSame($previous, $e->getPrevious());
        self::assertFalse($e->isPermanent());
        self::assertSame('WhatsApp host transport failed (RuntimeException).', $e->getMessage());

        self::assertTrue(GraphApiException::hostTransportFailure(new UnrecoverableMessageHandlingException('x'))->isPermanent());
    }

    public function testInvalidSendResponseIsPermanent(): void
    {
        $e = GraphApiException::invalidSendResponse();
        self::assertSame(0, $e->httpStatus);
        self::assertTrue($e->isPermanent());
        self::assertSame('WhatsApp transport response has no message id.', $e->getMessage());
    }
}
