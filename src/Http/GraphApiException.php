<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Http;

use Nowo\WhatsappBusinessBundle\Exception\WhatsappBusinessException;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;
use Throwable;

use function is_array;
use function is_int;
use function is_string;

/**
 * Graph API error envelope. Messages and dumped state never include access tokens.
 *
 * It is also the failure contract of every transport, host transports included: {@see self::isPermanent()} tells the
 * Messenger handler whether a retry can ever succeed (4xx except 429, a host exception implementing Messenger's
 * `UnrecoverableExceptionInterface`, or a send response without a message id) or not (status 0, 429, 5xx).
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
     * Creates the exception; the message and the raw payload are redacted.
     *
     * `$permanent` (since 1.4) marks a failure that no retry can fix even though its status is not a 4xx.
     *
     * @param array<string, mixed> $raw
     */
    public function __construct(
        string $message,
        public readonly int $httpStatus,
        public readonly ?int $graphCode,
        public readonly ?int $subcode,
        public readonly ?string $fbtraceId,
        array $raw,
        ?Throwable $previous = null,
        private readonly bool $permanent = false,
    ) {
        $this->raw = SecretRedactor::redactArray($raw);
        parent::__construct(SecretRedactor::redactString($message), $httpStatus, $previous);
    }

    /**
     * Whether a retry can never succeed: Graph 4xx except 429, or a failure created as permanent.
     *
     * Status 0 (network / timeout / host transport failure), 429 and 5xx are transient.
     */
    public function isPermanent(): bool
    {
        return $this->permanent || ($this->httpStatus >= 400 && $this->httpStatus < 500 && $this->httpStatus !== 429);
    }

    /**
     * Wraps anything other than a GraphApiException thrown by a host transport (`transport: custom`).
     *
     * The previous exception is kept for the host's own diagnostics, but its message is not copied (it may carry
     * secrets). The failure is transient (status 0, retried by Messenger) unless the host exception implements
     * Messenger's `UnrecoverableExceptionInterface`.
     */
    public static function hostTransportFailure(Throwable $previous): self
    {
        return new self(
            'WhatsApp host transport failed (' . $previous::class . ').',
            0,
            null,
            null,
            null,
            [],
            $previous,
            $previous instanceof UnrecoverableExceptionInterface,
        );
    }

    /**
     * A host transport answered a send without `messages[0].id`. Permanent: the gateway may already have sent the
     * message, so a retry could deliver it twice.
     */
    public static function invalidSendResponse(): self
    {
        return new self('WhatsApp transport response has no message id.', 0, null, null, null, [], null, true);
    }

    /**
     * Creates the exception from a Graph error payload (message, code, subcode, trace id).
     *
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

    /**
     * Creates the exception for a network/timeout failure before any Graph response.
     */
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

    /**
     * Creates the exception for a media URL outside the download allow-list.
     */
    public static function mediaUrlRejected(): self
    {
        return new self('WhatsApp media download URL is not allowed.', 0, null, null, null, []);
    }

    /**
     * Returns the dump representation without the raw payload.
     *
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
            'permanent'  => $this->isPermanent(),
            'raw'        => '[omitted]',
        ];
    }
}
