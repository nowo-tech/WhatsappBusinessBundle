<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Message;

/**
 * Recipient + message + optional Cloud API extras.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class OutgoingEnvelope
{
    /**
     * Creates the envelope: recipient, message and optional reply context / callback data.
     */
    public function __construct(
        public PhoneNumber $to,
        public OutgoingMessage $message,
        public ?string $replyToMessageId = null,
        public ?string $bizOpaqueCallbackData = null,
    ) {
    }

    /**
     * Full POST /{phone-number-id}/messages JSON body.
     *
     * @return array<string, mixed>
     */
    public function toGraphBody(): array
    {
        $body = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $this->to->digits,
            'type'              => $this->message->type()->value,
        ];

        $body += $this->message->graphFields();

        if ($this->replyToMessageId !== null && $this->replyToMessageId !== '') {
            $body['context'] = ['message_id' => $this->replyToMessageId];
        }

        if ($this->bizOpaqueCallbackData !== null && $this->bizOpaqueCallbackData !== '') {
            $body['biz_opaque_callback_data'] = $this->bizOpaqueCallbackData;
        }

        return $body;
    }
}
