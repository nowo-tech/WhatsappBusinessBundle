<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Message;

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
}
