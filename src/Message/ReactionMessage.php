<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Message;

/**
 * Emoji reaction to an existing message id.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ReactionMessage implements OutgoingMessage
{
    public function __construct(
        public string $messageId,
        public string $emoji,
    ) {
    }

    public function type(): MessageType
    {
        return MessageType::Reaction;
    }

    public function graphFields(): array
    {
        return [
            'reaction' => [
                'message_id' => $this->messageId,
                'emoji'      => $this->emoji,
            ],
        ];
    }
}
