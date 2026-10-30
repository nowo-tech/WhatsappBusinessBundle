<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Message;

/**
 * Contacts or interactive payloads supplied as Graph-shaped arrays by the host.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class GraphPayloadMessage implements OutgoingMessage
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
     * Creates a contacts message from Graph contact cards.
     *
     * @param list<array<string, mixed>> $contacts
     */
    public static function contacts(array $contacts): self
    {
        return new self(MessageType::Contacts, ['contacts' => $contacts]);
    }

    /**
     * Creates an interactive (buttons/list) message from a Graph interactive object.
     *
     * @param array<string, mixed> $interactive
     */
    public static function interactive(array $interactive): self
    {
        return new self(MessageType::Interactive, ['interactive' => $interactive]);
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
