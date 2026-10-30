<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Webhook;

use function array_keys;
use function array_map;
use function is_array;
use function is_scalar;
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
     * Extracts every inbound message and status from a Graph webhook payload.
     *
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

    /**
     * Returns the distinct `metadata.phone_number_id` values of every change in a Graph webhook payload.
     *
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    public function phoneNumberIds(array $payload): array
    {
        $ids     = [];
        $entries = $payload['entry'] ?? [];
        if (!is_array($entries)) {
            return [];
        }
        foreach ($entries as $entry) {
            $changes = is_array($entry) ? ($entry['changes'] ?? []) : [];
            if (!is_array($changes)) {
                continue;
            }
            foreach ($changes as $change) {
                $value    = is_array($change) ? ($change['value'] ?? []) : [];
                $metadata = is_array($value) ? ($value['metadata'] ?? []) : [];
                if (is_array($metadata) && isset($metadata['phone_number_id']) && is_scalar($metadata['phone_number_id'])) {
                    $ids[(string) $metadata['phone_number_id']] = true;
                }
            }
        }

        return array_map('strval', array_keys($ids));
    }
}
