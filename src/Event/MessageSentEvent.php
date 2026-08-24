<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Event;

use Nowo\WhatsappBusinessBundle\Message\MessageResult;
use Nowo\WhatsappBusinessBundle\Message\OutgoingEnvelope;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after a successful Cloud API send.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class MessageSentEvent extends Event
{
    public function __construct(
        public readonly string $profile,
        public readonly OutgoingEnvelope $envelope,
        public readonly MessageResult $result,
    ) {
    }
}
