<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Messenger;

use Nowo\WhatsappBusinessBundle\Client\WhatsappClientRegistryInterface;
use Nowo\WhatsappBusinessBundle\Message\MessageResult;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles queued outbound messages. Failures bubble to Messenger retry/failure transport.
 *
 * Registered through #[AsMessageHandler] (REQ-MSG-001); the bundle enables autoconfiguration for it.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsMessageHandler]
final readonly class SendOutgoingMessageHandler
{
    /**
     * Creates the handler with the profile registry.
     */
    public function __construct(
        private WhatsappClientRegistryInterface $registry,
    ) {
    }

    /**
     * Sends the queued envelope with the requested profile; failures are rethrown so Messenger can retry.
     */
    public function __invoke(SendOutgoingMessage $message): MessageResult
    {
        return $this->registry->get($message->profile)->send($message->envelope);
    }
}
