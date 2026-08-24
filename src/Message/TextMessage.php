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
    public function __construct(
        public string $body,
        public bool $previewUrl = false,
    ) {
    }

    public function type(): MessageType
    {
        return MessageType::Text;
    }

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
