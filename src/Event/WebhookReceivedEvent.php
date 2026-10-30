<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Event;

use Nowo\WhatsappBusinessBundle\Webhook\WebhookNotification;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched once per verified POST webhook (before per-item events).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class WebhookReceivedEvent extends Event
{
    /**
     * Creates the event for a verified webhook notification (all messages and statuses).
     */
    public function __construct(
        public readonly string $profile,
        public readonly WebhookNotification $notification,
    ) {
    }
}
