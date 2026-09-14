<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Message;

use Nowo\WhatsappBusinessBundle\Http\SecretRedactor;

/**
 * Binary download of GET /{media-id} followed by the media URL.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MediaDownload
{
    public function __construct(
        public string $contents,
        public ?string $mimeType,
        public ?string $url,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'contents' => '[omitted]',
            'mimeType' => $this->mimeType,
            'url'      => SecretRedactor::redactUrl($this->url),
        ];
    }
}
