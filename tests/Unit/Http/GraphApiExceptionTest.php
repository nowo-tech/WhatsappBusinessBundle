<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Tests\Unit\Http;

use Nowo\WhatsappBusinessBundle\Http\GraphApiException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

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
    }

    public function testFromGraphWithoutError(): void
    {
        $e = GraphApiException::fromGraph([], 500);
        self::assertSame('WhatsApp Cloud API request failed.', $e->getMessage());
        self::assertNull($e->graphCode);
    }
}
