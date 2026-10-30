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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function count;
use function is_array;
use function json_decode;

/**
 * Cloud API webhook: GET subscribe challenge + POST signed notifications.
 *
 * Registered explicitly (public, `controller.service_arguments`, `autoconfigure: false`) so its #[Route]
 * attributes are only loaded when the host imports the bundle routing (never through `routing.controllers`).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class WebhookController
{
    private const INVALID_VERIFY_TOKEN = 'Invalid webhook verify token.';
    private const INVALID_SIGNATURE    = 'Invalid WhatsApp webhook signature.';

    /**
     * Creates the webhook endpoint with the profile registry, verifier, parser and event dispatcher.
     */
    public function __construct(
        private readonly WhatsappClientRegistryInterface $registry,
        private readonly WebhookVerifier $verifier,
        private readonly WebhookParser $parser,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Answers the GET subscribe challenge or verifies, parses and dispatches a signed POST notification.
     *
     * Route names and paths are stable since 1.0 (REQ-SF-004 / REQ-BC-001).
     */
    #[Route('/whatsapp/webhook', name: 'nowo_whatsapp_business_webhook', defaults: ['profile' => null], methods: ['GET', 'POST'])]
    #[Route('/whatsapp/webhook/{profile}', name: 'nowo_whatsapp_business_webhook_profile', requirements: ['profile' => '[a-zA-Z0-9_-]+'], methods: ['GET', 'POST'])]
    public function __invoke(Request $request, ?string $profile = null): Response
    {
        try {
            $client = $this->registry->get($profile === '' ? null : $profile);
        } catch (UnknownProfileException) {
            $this->logger->warning('WhatsApp webhook for unknown profile.', ['profile' => $profile]);

            // Same status and body as a known profile with a bad token/signature: no profile-name enumeration.
            return $request->isMethod('GET')
                ? new Response(self::INVALID_VERIFY_TOKEN, Response::HTTP_FORBIDDEN)
                : new Response(self::INVALID_SIGNATURE, Response::HTTP_FORBIDDEN);
        }

        $cloudProfile = $client->profile();

        if ($request->isMethod('GET')) {
            $mode      = (string) $request->query->get('hub_mode', $request->query->get('hub.mode', ''));
            $token     = (string) $request->query->get('hub_verify_token', $request->query->get('hub.verify_token', ''));
            $challenge = (string) $request->query->get('hub_challenge', $request->query->get('hub.challenge', ''));

            if (!$this->verifier->verifySubscribe($cloudProfile, $mode, $token)) {
                return new Response(self::INVALID_VERIFY_TOKEN, Response::HTTP_FORBIDDEN);
            }

            return new Response($challenge, Response::HTTP_OK, ['Content-Type' => 'text/plain']);
        }

        $rawBody = $request->getContent();
        $header  = $request->headers->get('X-Hub-Signature-256');
        try {
            $this->verifier->verifySignature($cloudProfile, $rawBody, $header);
        } catch (WebhookVerificationException) {
            $this->logger->warning('WhatsApp webhook signature rejected.', ['profile' => $cloudProfile->name]);

            return new Response(self::INVALID_SIGNATURE, Response::HTTP_FORBIDDEN);
        }

        /** @var mixed $decoded */
        $decoded = json_decode($rawBody, true);
        $payload = is_array($decoded) ? $decoded : [];

        // Bind the signed payload to the profile in the URL: profiles of one Meta app share the app secret,
        // so a body signed for another phone number must not be replayed onto this profile.
        foreach ($this->parser->phoneNumberIds($payload) as $phoneNumberId) {
            if ($phoneNumberId !== $cloudProfile->phoneNumberId) {
                $this->logger->warning('WhatsApp webhook payload for another phone number id rejected.', ['profile' => $cloudProfile->name]);

                return new Response('WhatsApp webhook payload does not belong to this profile.', Response::HTTP_FORBIDDEN);
            }
        }

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
