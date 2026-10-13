<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Http;

use Nowo\WhatsappBusinessBundle\Exception\WhatsappBusinessException;

use function is_array;
use function is_int;
use function is_string;

/**
 * Graph API error envelope. Messages and dumped state never include access tokens.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class GraphApiException extends WhatsappBusinessException
{
    /**
     * @var array<string, mixed>
     */
    public readonly array $raw;

    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        string $message,
        public readonly int $httpStatus,
        public readonly ?int $graphCode,
        public readonly ?int $subcode,
        public readonly ?string $fbtraceId,
        array $raw,
    ) {
        $this->raw = SecretRedactor::redactArray($raw);
        parent::__construct(SecretRedactor::redactString($message), $httpStatus);
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

    public static function transportFailure(): self
    {
        return new self('WhatsApp Cloud API transport failure.', 0, null, null, null, []);
    }

    /**
     * The Graph response body is not valid JSON (e.g. an HTML error page from a proxy).
     */
    public static function invalidResponse(int $httpStatus): self
    {
        return new self('WhatsApp Cloud API returned a non-JSON response.', $httpStatus, null, null, null, []);
    }

    /**
     * Media download answered with a 3xx/4xx/5xx status.
     */
    public static function mediaDownloadFailed(int $httpStatus): self
    {
        return new self('WhatsApp media download failed.', $httpStatus, null, null, null, []);
    }

    public static function mediaUrlRejected(): self
    {
        return new self('WhatsApp media download URL is not allowed.', 0, null, null, null, []);
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'message'    => $this->getMessage(),
            'httpStatus' => $this->httpStatus,
            'graphCode'  => $this->graphCode,
            'subcode'    => $this->subcode,
            'fbtraceId'  => $this->fbtraceId,
            'raw'        => '[omitted]',
        ];
    }
}
