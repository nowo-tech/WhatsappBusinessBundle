<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Webhook;

use function is_array;
use function is_string;

/**
 * Turns Graph webhook JSON into typed notifications.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class WebhookParser
{
    /**
     * @param array<string, mixed> $payload
     */
    public function parse(array $payload): WebhookNotification
    {
        $object   = isset($payload['object']) && is_string($payload['object']) ? $payload['object'] : '';
        $messages = [];
        $statuses = [];
        $entries  = $payload['entry'] ?? [];
        if (!is_array($entries)) {
            return new WebhookNotification($object, [], [], $payload);
        }

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $changes = $entry['changes'] ?? [];
            if (!is_array($changes)) {
                continue;
            }
            foreach ($changes as $change) {
                if (!is_array($change)) {
                    continue;
                }
                $value = $change['value'] ?? [];
                if (!is_array($value)) {
                    continue;
                }
                $incoming = $value['messages'] ?? [];
                if (is_array($incoming)) {
                    foreach ($incoming as $item) {
                        if (is_array($item)) {
                            $messages[] = IncomingMessage::fromGraph($item);
                        }
                    }
                }
                $statusList = $value['statuses'] ?? [];
                if (is_array($statusList)) {
                    foreach ($statusList as $item) {
                        if (is_array($item)) {
                            $statuses[] = StatusUpdate::fromGraph($item);
                        }
                    }
                }
            }
        }

        return new WebhookNotification($object, $messages, $statuses, $payload);
    }
}
