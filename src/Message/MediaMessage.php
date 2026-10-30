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
    /**
     * Creates a media message; only image, audio, video, document and sticker types are accepted.
     */
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

    /**
     * Creates an image message with an optional caption.
     */
    public static function image(MediaSource $source, ?string $caption = null): self
    {
        return new self(MessageType::Image, $source, $caption);
    }

    /**
     * Creates an audio message.
     */
    public static function audio(MediaSource $source): self
    {
        return new self(MessageType::Audio, $source);
    }

    /**
     * Creates a video message with an optional caption.
     */
    public static function video(MediaSource $source, ?string $caption = null): self
    {
        return new self(MessageType::Video, $source, $caption);
    }

    /**
     * Creates a document message with optional caption and file name.
     */
    public static function document(MediaSource $source, ?string $caption = null, ?string $filename = null): self
    {
        return new self(MessageType::Document, $source, $caption, $filename);
    }

    /**
     * Creates a sticker message.
     */
    public static function sticker(MediaSource $source): self
    {
        return new self(MessageType::Sticker, $source);
    }

    /**
     * Returns the media type of this message.
     */
    public function type(): MessageType
    {
        return $this->mediaType;
    }

    /**
     * Returns the Graph media field (id or link, plus caption/filename when set).
     */
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
