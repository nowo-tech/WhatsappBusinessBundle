<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Message;

/**
 * Uploaded media identifier returned by POST /{phone-number-id}/media.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MediaId
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public string $id,
        public array $raw,
    ) {
    }
}
