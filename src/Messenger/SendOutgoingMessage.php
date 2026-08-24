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
    public function __construct(
        public OutgoingEnvelope $envelope,
        public ?string $profile = null,
    ) {
    }
}
