<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Message;

use function is_string;

/**
 * Host-supplied Graph fields (migration / advanced payloads).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class RawGraphMessage implements OutgoingMessage
{
    /**
     * Creates a message from a Graph type and its type-specific fields.
     *
     * @param array<string, mixed> $fields
     */
    public function __construct(
        public MessageType $messageType,
        public array $fields,
    ) {
    }

    /**
     * Creates a message from a full Graph body, dropping transport fields (`to`, `type`, `messaging_product`).
     *
     * @param array<string, mixed> $graphBody
     */
    public static function fromGraphBody(array $graphBody): self
    {
        $typeValue = isset($graphBody['type']) && is_string($graphBody['type']) ? $graphBody['type'] : MessageType::Text->value;
        $type      = MessageType::tryFrom($typeValue) ?? MessageType::Text;
        unset($graphBody['messaging_product'], $graphBody['recipient_type'], $graphBody['to'], $graphBody['type']);

        return new self($type, $graphBody);
    }

    /**
     * Returns the Graph message type of this payload.
     */
    public function type(): MessageType
    {
        return $this->messageType;
    }

    /**
     * Returns the host-supplied Graph fields for this type.
     */
    public function graphFields(): array
    {
        return $this->fields;
    }
}
