<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\EventSubscriber;

use Nowo\WhatsappBusinessBundle\Transport\MockTransport;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Clears the sends recorded by mock transports at the start of every main request.
 *
 * Keeps {@see MockTransport::recorded()} scoped to the current request in long-lived workers even when
 * {@code services_resetter} does not run. Sub-requests keep the main request's records.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ResetMockTransportsOnRequestSubscriber implements EventSubscriberInterface
{
    /**
     * @param iterable<ResetInterface> $transports
     */
    public function __construct(
        private iterable $transports,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 4096]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        foreach ($this->transports as $transport) {
            $transport->reset();
        }
    }
}
