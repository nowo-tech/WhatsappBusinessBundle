<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Message;

use Nowo\WhatsappBusinessBundle\Http\SecretRedactor;

use function is_array;
use function is_string;

/**
 * Result of POST /{phone-number-id}/messages.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MessageResult
{
    /**
     * @param list<string> $recipientIds
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public string $messageId,
        public array $recipientIds,
        public array $raw,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromGraph(array $payload): self
    {
        $messages  = $payload['messages'] ?? [];
        $contacts  = $payload['contacts'] ?? [];
        $messageId = '';
        if (is_array($messages) && isset($messages[0]) && is_array($messages[0]) && isset($messages[0]['id']) && is_string($messages[0]['id'])) {
            $messageId = $messages[0]['id'];
        }

        $recipientIds = [];
        if (is_array($contacts)) {
            foreach ($contacts as $contact) {
                if (is_array($contact) && isset($contact['wa_id']) && is_string($contact['wa_id'])) {
                    $recipientIds[] = $contact['wa_id'];
                }
            }
        }

        return new self($messageId, $recipientIds, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $masked = [];
        foreach ($this->recipientIds as $id) {
            $masked[] = SecretRedactor::maskPhone($id);
        }

        return [
            'messageId'    => $this->messageId,
            'recipientIds' => $masked,
            'raw'          => '[omitted]',
        ];
    }
}
