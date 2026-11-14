<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Transport;

/**
 * Optional extension of {@see TransportInterface} for a host service shared by several `custom` profiles.
 *
 * {@see TransportInterface} carries no profile, so one service named by two profiles receives both profiles'
 * messages on one shared instance. When the service implements this interface, each profile's client calls
 * {@see self::forProfile()} once, when the client is built, and sends through the transport it returns.
 *
 * The returned transport lives as long as the container (a kept-alive FrankenPHP worker included): keep it stateless,
 * as any host transport. Since 1.4.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface ProfileAwareTransportInterface extends TransportInterface
{
    /**
     * Returns the transport that serves the profile named $profile (the key under
     * `nowo_whatsapp_business.profiles`).
     */
    public function forProfile(string $profile): TransportInterface;
}
