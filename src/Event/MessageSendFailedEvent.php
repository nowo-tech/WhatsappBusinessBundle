<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Event;

use Nowo\WhatsappBusinessBundle\Http\GraphApiException;
use Nowo\WhatsappBusinessBundle\Message\OutgoingEnvelope;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when Graph API rejects or cannot send a message.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MessageSendFailedEvent extends Event
{
    /**
     * Creates the event for an outbound message the Cloud API rejected or could not receive.
     */
    public function __construct(
        public readonly string $profile,
        public readonly OutgoingEnvelope $envelope,
        public readonly GraphApiException $exception,
    ) {
    }
}
