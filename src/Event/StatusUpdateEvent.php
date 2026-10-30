<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Event;

use Nowo\WhatsappBusinessBundle\Webhook\StatusUpdate;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched for each delivery/read status on a verified webhook.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class StatusUpdateEvent extends Event
{
    /**
     * Creates the event for one delivery/read status received on a profile webhook.
     */
    public function __construct(
        public readonly string $profile,
        public readonly StatusUpdate $status,
    ) {
    }
}
