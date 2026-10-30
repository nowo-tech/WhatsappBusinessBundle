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
     * Creates the uploaded media id with the raw Graph response.
     *
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public string $id,
        public array $raw,
    ) {
    }

    /**
     * Returns the dump representation without the raw payload.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'id'  => $this->id,
            'raw' => '[omitted]',
        ];
    }
}
