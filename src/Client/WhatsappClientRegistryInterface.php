<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Client;

/**
 * Resolves named Cloud API profiles.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface WhatsappClientRegistryInterface
{
    /**
     * Returns the client of the given profile, or of the default profile when null.
     */
    public function get(?string $profile = null): WhatsappClientInterface;

    /**
     * Tells whether a profile with this name is configured.
     */
    public function has(string $profile): bool;

    /**
     * Returns the configured profile names.
     *
     * @return list<string>
     */
    public function names(): array;
}
