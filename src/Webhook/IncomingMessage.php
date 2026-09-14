<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Webhook;

use Nowo\WhatsappBusinessBundle\Http\SecretRedactor;

use function is_array;
use function is_string;

/**
 * One inbound user message extracted from a Cloud API webhook.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class IncomingMessage
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public string $id,
        public string $from,
        public string $type,
        public ?string $text,
        public string $timestamp,
        public array $raw,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromGraph(array $payload): self
    {
        $id        = isset($payload['id']) && is_string($payload['id']) ? $payload['id'] : '';
        $from      = isset($payload['from']) && is_string($payload['from']) ? $payload['from'] : '';
        $type      = isset($payload['type']) && is_string($payload['type']) ? $payload['type'] : '';
        $timestamp = isset($payload['timestamp']) && is_string($payload['timestamp']) ? $payload['timestamp'] : '';
        $text      = null;
        if (isset($payload['text']) && is_array($payload['text']) && isset($payload['text']['body']) && is_string($payload['text']['body'])) {
            $text = $payload['text']['body'];
        }

        return new self($id, $from, $type, $text, $timestamp, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'id'        => $this->id,
            'from'      => SecretRedactor::maskPhone($this->from),
            'type'      => $this->type,
            'text'      => $this->text === null ? null : '[omitted]',
            'timestamp' => $this->timestamp,
            'raw'       => '[omitted]',
        ];
    }
}
