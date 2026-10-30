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
    /**
     * Creates an emoji reaction to an existing message id.
     */
    public function __construct(
        public string $messageId,
        public string $emoji,
    ) {
    }

    /**
     * Returns the reaction message type.
     */
    public function type(): MessageType
    {
        return MessageType::Reaction;
    }

    /**
     * Returns the Graph `reaction` field.
     */
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
