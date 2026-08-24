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
     * @param array<string, mixed> $fields
     */
    public function __construct(
        public MessageType $messageType,
        public array $fields,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $contacts
     */
    public static function contacts(array $contacts): self
    {
        return new self(MessageType::Contacts, ['contacts' => $contacts]);
    }

    /**
     * @param array<string, mixed> $interactive
     */
    public static function interactive(array $interactive): self
    {
        return new self(MessageType::Interactive, ['interactive' => $interactive]);
    }

    public function type(): MessageType
    {
        return $this->messageType;
    }

    public function graphFields(): array
    {
        return $this->fields;
    }
}
