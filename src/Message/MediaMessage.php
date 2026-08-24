<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Message;

use InvalidArgumentException;

use function in_array;

/**
 * Image, audio, video, document, or sticker payload.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MediaMessage implements OutgoingMessage
{
    public function __construct(
        public MessageType $mediaType,
        public MediaSource $source,
        public ?string $caption = null,
        public ?string $filename = null,
    ) {
        if (
            !in_array($mediaType, [MessageType::Image, MessageType::Audio, MessageType::Video, MessageType::Document, MessageType::Sticker], true)
        ) {
            throw new InvalidArgumentException('MediaMessage requires an image, audio, video, document, or sticker type.');
        }
    }

    public static function image(MediaSource $source, ?string $caption = null): self
    {
        return new self(MessageType::Image, $source, $caption);
    }

    public static function audio(MediaSource $source): self
    {
        return new self(MessageType::Audio, $source);
    }

    public static function video(MediaSource $source, ?string $caption = null): self
    {
        return new self(MessageType::Video, $source, $caption);
    }

    public static function document(MediaSource $source, ?string $caption = null, ?string $filename = null): self
    {
        return new self(MessageType::Document, $source, $caption, $filename);
    }

    public static function sticker(MediaSource $source): self
    {
        return new self(MessageType::Sticker, $source);
    }

    public function type(): MessageType
    {
        return $this->mediaType;
    }

    public function graphFields(): array
    {
        $field = $this->source->toGraphField();
        if ($this->caption !== null && $this->caption !== '') {
            $field['caption'] = $this->caption;
        }
        if ($this->filename !== null && $this->filename !== '') {
            $field['filename'] = $this->filename;
        }

        return [$this->mediaType->value => $field];
    }
}
