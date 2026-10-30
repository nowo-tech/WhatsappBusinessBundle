<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Messenger;

use Nowo\WhatsappBusinessBundle\Client\WhatsappClientRegistryInterface;
use Nowo\WhatsappBusinessBundle\Exception\UnknownProfileException;
use Nowo\WhatsappBusinessBundle\Http\GraphApiException;
use Nowo\WhatsappBusinessBundle\Message\MessageResult;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Handles queued outbound messages. Transient failures bubble to the Messenger retry strategy; permanent
 * ones go straight to the failure transport.
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
     * Sends the queued envelope with the requested profile.
     *
     * Unknown profiles and permanent Graph 4xx errors (except 429) are wrapped in
     * UnrecoverableMessageHandlingException (no retry, straight to the failure transport); 429, 5xx and
     * transport failures are rethrown so Messenger retries them.
     */
    public function __invoke(SendOutgoingMessage $message): MessageResult
    {
        try {
            return $this->registry->get($message->profile)->send($message->envelope);
        } catch (UnknownProfileException $exception) {
            throw new UnrecoverableMessageHandlingException($exception->getMessage(), 0, $exception);
        } catch (GraphApiException $exception) {
            // Permanent Graph errors (4xx except 429) never succeed on retry; 429, 5xx and transport failures
            // (status 0) are rethrown so the transport retry_strategy applies.
            if ($exception->httpStatus >= 400 && $exception->httpStatus < 500 && $exception->httpStatus !== 429) {
                throw new UnrecoverableMessageHandlingException($exception->getMessage(), $exception->httpStatus, $exception);
            }

            throw $exception;
        }
    }
}
