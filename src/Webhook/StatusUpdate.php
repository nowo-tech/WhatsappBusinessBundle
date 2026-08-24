<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Webhook;

use function is_string;

/**
 * Delivery / read / failed status from a Cloud API webhook.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class StatusUpdate
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public string $id,
        public string $status,
        public string $timestamp,
        public ?string $recipientId,
        public array $raw,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromGraph(array $payload): self
    {
        $id          = isset($payload['id']) && is_string($payload['id']) ? $payload['id'] : '';
        $status      = isset($payload['status']) && is_string($payload['status']) ? $payload['status'] : '';
        $timestamp   = isset($payload['timestamp']) && is_string($payload['timestamp']) ? $payload['timestamp'] : '';
        $recipientId = isset($payload['recipient_id']) && is_string($payload['recipient_id']) ? $payload['recipient_id'] : null;

        return new self($id, $status, $timestamp, $recipientId, $payload);
    }
}
