<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Http;

use Nowo\WhatsappBusinessBundle\Exception\WhatsappBusinessException;

use function is_array;
use function is_int;
use function is_string;

/**
 * Graph API error envelope.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class GraphApiException extends WhatsappBusinessException
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        string $message,
        public readonly int $httpStatus,
        public readonly ?int $graphCode,
        public readonly ?int $subcode,
        public readonly ?string $fbtraceId,
        public readonly array $raw,
    ) {
        parent::__construct($message, $httpStatus);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromGraph(array $payload, int $httpStatus): self
    {
        $error     = $payload['error'] ?? null;
        $message   = 'WhatsApp Cloud API request failed.';
        $code      = null;
        $subcode   = null;
        $fbtraceId = null;

        if (is_array($error)) {
            if (isset($error['message']) && is_string($error['message'])) {
                $message = $error['message'];
            }
            if (isset($error['code']) && is_int($error['code'])) {
                $code = $error['code'];
            }
            if (isset($error['error_subcode']) && is_int($error['error_subcode'])) {
                $subcode = $error['error_subcode'];
            }
            if (isset($error['fbtrace_id']) && is_string($error['fbtrace_id'])) {
                $fbtraceId = $error['fbtrace_id'];
            }
        }

        return new self($message, $httpStatus, $code, $subcode, $fbtraceId, $payload);
    }
}
