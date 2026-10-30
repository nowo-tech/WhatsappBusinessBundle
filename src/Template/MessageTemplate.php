<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Template;

use function is_string;

/**
 * One item from GET /{waba-id}/message_templates.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MessageTemplate
{
    /**
     * Creates one message template entry.
     *
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public string $id,
        public string $name,
        public TemplateStatus $status,
        public string $language,
        public string $category,
        public array $raw,
    ) {
    }

    /**
     * Creates the entry from one Graph message_templates item.
     *
     * @param array<string, mixed> $payload
     */
    public static function fromGraph(array $payload): self
    {
        $id       = isset($payload['id']) && is_string($payload['id']) ? $payload['id'] : '';
        $name     = isset($payload['name']) && is_string($payload['name']) ? $payload['name'] : '';
        $status   = isset($payload['status']) && is_string($payload['status']) ? $payload['status'] : 'UNKNOWN';
        $language = isset($payload['language']) && is_string($payload['language']) ? $payload['language'] : '';
        $category = isset($payload['category']) && is_string($payload['category']) ? $payload['category'] : '';

        return new self($id, $name, TemplateStatus::fromGraph($status), $language, $category, $payload);
    }
}
