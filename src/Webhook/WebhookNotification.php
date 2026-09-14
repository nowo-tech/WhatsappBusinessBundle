<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Webhook;

/**
 * Parsed Cloud API webhook (messages + statuses).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class WebhookNotification
{
    /**
     * @param list<IncomingMessage> $messages
     * @param list<StatusUpdate> $statuses
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public string $object,
        public array $messages,
        public array $statuses,
        public array $raw,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'object'   => $this->object,
            'messages' => $this->messages,
            'statuses' => $this->statuses,
            'raw'      => '[omitted]',
        ];
    }
}
