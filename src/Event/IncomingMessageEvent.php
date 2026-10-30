<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Event;

use Nowo\WhatsappBusinessBundle\Webhook\IncomingMessage;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched for each inbound user message on a verified webhook.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class IncomingMessageEvent extends Event
{
    /**
     * Creates the event for one inbound message received on a profile webhook.
     */
    public function __construct(
        public readonly string $profile,
        public readonly IncomingMessage $message,
    ) {
    }
}
