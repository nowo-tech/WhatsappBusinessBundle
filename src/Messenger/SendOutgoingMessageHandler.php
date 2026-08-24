<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Messenger;

use Nowo\WhatsappBusinessBundle\Client\WhatsappClientRegistryInterface;
use Nowo\WhatsappBusinessBundle\Message\MessageResult;

/**
 * Handles queued outbound messages. Failures bubble to Messenger retry/failure transport.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class SendOutgoingMessageHandler
{
    public function __construct(
        private WhatsappClientRegistryInterface $registry,
    ) {
    }

    public function __invoke(SendOutgoingMessage $message): MessageResult
    {
        return $this->registry->get($message->profile)->send($message->envelope);
    }
}
