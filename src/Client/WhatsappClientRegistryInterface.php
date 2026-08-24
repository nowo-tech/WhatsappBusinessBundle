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
    public function get(?string $profile = null): WhatsappClientInterface;

    public function has(string $profile): bool;

    /**
     * @return list<string>
     */
    public function names(): array;
}
