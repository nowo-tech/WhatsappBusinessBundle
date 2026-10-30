<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Message;

/**
 * Plain-text outbound message.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class TextMessage implements OutgoingMessage
{
    /**
     * Creates a plain-text message, optionally with link previews.
     */
    public function __construct(
        public string $body,
        public bool $previewUrl = false,
    ) {
    }

    /**
     * Returns the text message type.
     */
    public function type(): MessageType
    {
        return MessageType::Text;
    }

    /**
     * Returns the Graph `text` field.
     */
    public function graphFields(): array
    {
        return [
            'text' => [
                'preview_url' => $this->previewUrl,
                'body'        => $this->body,
            ],
        ];
    }
}
