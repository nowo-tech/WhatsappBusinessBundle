<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Webhook;

use Nowo\WhatsappBusinessBundle\Client\WhatsappClientRegistryInterface;
use Nowo\WhatsappBusinessBundle\Event\IncomingMessageEvent;
use Nowo\WhatsappBusinessBundle\Event\StatusUpdateEvent;
use Nowo\WhatsappBusinessBundle\Event\WebhookReceivedEvent;
use Nowo\WhatsappBusinessBundle\Exception\UnknownProfileException;
use Nowo\WhatsappBusinessBundle\Exception\WebhookVerificationException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function count;
use function is_array;
use function json_decode;

/**
 * Cloud API webhook: GET subscribe challenge + POST signed notifications.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[AsController]
final class WebhookController
{
    public function __construct(
        private readonly WhatsappClientRegistryInterface $registry,
        private readonly WebhookVerifier $verifier,
        private readonly WebhookParser $parser,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function __invoke(Request $request, ?string $profile = null): Response
    {
        try {
            $client = $this->registry->get($profile === '' ? null : $profile);
        } catch (UnknownProfileException) {
            $this->logger->warning('WhatsApp webhook for unknown profile.', ['profile' => $profile]);

            return new Response('Unknown WhatsApp Business profile.', Response::HTTP_NOT_FOUND);
        }

        $cloudProfile = $client->profile();

        if ($request->isMethod('GET')) {
            $mode      = (string) $request->query->get('hub_mode', $request->query->get('hub.mode', ''));
            $token     = (string) $request->query->get('hub_verify_token', $request->query->get('hub.verify_token', ''));
            $challenge = (string) $request->query->get('hub_challenge', $request->query->get('hub.challenge', ''));

            if (!$this->verifier->verifySubscribe($cloudProfile, $mode, $token)) {
                return new Response('Invalid webhook verify token.', Response::HTTP_FORBIDDEN);
            }

            return new Response($challenge, Response::HTTP_OK, ['Content-Type' => 'text/plain']);
        }

        $rawBody = $request->getContent();
        $header  = $request->headers->get('X-Hub-Signature-256');
        try {
            $this->verifier->verifySignature($cloudProfile, $rawBody, $header);
        } catch (WebhookVerificationException) {
            $this->logger->warning('WhatsApp webhook signature rejected.', ['profile' => $cloudProfile->name]);

            return new Response('Invalid WhatsApp webhook signature.', Response::HTTP_FORBIDDEN);
        }

        /** @var mixed $decoded */
        $decoded      = json_decode($rawBody, true);
        $payload      = is_array($decoded) ? $decoded : [];
        $notification = $this->parser->parse($payload);

        $this->eventDispatcher->dispatch(new WebhookReceivedEvent($cloudProfile->name, $notification));
        foreach ($notification->messages as $message) {
            $this->eventDispatcher->dispatch(new IncomingMessageEvent($cloudProfile->name, $message));
        }
        foreach ($notification->statuses as $status) {
            $this->eventDispatcher->dispatch(new StatusUpdateEvent($cloudProfile->name, $status));
        }

        $this->logger->info('WhatsApp webhook accepted.', [
            'profile'  => $cloudProfile->name,
            'messages' => count($notification->messages),
            'statuses' => count($notification->statuses),
        ]);

        return new Response('', Response::HTTP_OK);
    }
}
