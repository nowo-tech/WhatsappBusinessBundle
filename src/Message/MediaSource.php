<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Message;

use InvalidArgumentException;

use function str_starts_with;

/**
 * Cloud API media: HTTPS link or uploaded media id.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MediaSource
{
    private function __construct(
        public ?string $id,
        public ?string $link,
    ) {
    }

    public static function id(string $mediaId): self
    {
        if ($mediaId === '') {
            throw new InvalidArgumentException('Media id cannot be empty.');
        }

        return new self($mediaId, null);
    }

    public static function link(string $url): self
    {
        if (!str_starts_with($url, 'https://')) {
            throw new InvalidArgumentException('Media link must be an https:// URL.');
        }

        return new self(null, $url);
    }

    /**
     * @return array{id: string}|array{link: string}
     */
    public function toGraphField(): array
    {
        if ($this->id !== null) {
            return ['id' => $this->id];
        }

        return ['link' => (string) $this->link];
    }
}
