<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Message;

/**
 * Typed outbound payload (Graph `type` + type-specific fields).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface OutgoingMessage
{
    /**
     * Returns the Graph `type` of this message.
     */
    public function type(): MessageType;

    /**
     * Graph fields for this type (without `messaging_product`, `to`, `type`).
     *
     * @return array<string, mixed>
     */
    public function graphFields(): array;
}
