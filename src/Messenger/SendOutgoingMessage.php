<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Messenger;

use Nowo\WhatsappBusinessBundle\Message\OutgoingEnvelope;

/**
 * Queue an outbound Cloud API message.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class SendOutgoingMessage
{
    /**
     * Creates the message for an envelope and an optional profile name (default profile when null).
     */
    public function __construct(
        public OutgoingEnvelope $envelope,
        public ?string $profile = null,
    ) {
    }
}
