<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\Client;

use Nowo\WhatsappBusinessBundle\Exception\UnknownProfileException;

use function array_keys;

/**
 * In-memory map of profile name → client.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class WhatsappClientRegistry implements WhatsappClientRegistryInterface
{
    /**
     * @param array<string, WhatsappClientInterface> $clients
     */
    public function __construct(
        private array $clients,
        private string $defaultProfile,
    ) {
        if (!isset($this->clients[$this->defaultProfile])) {
            throw UnknownProfileException::forProfile($this->defaultProfile);
        }
    }

    public function get(?string $profile = null): WhatsappClientInterface
    {
        $name = $profile ?? $this->defaultProfile;
        if (!isset($this->clients[$name])) {
            throw UnknownProfileException::forProfile($name);
        }

        return $this->clients[$name];
    }

    public function has(string $profile): bool
    {
        return isset($this->clients[$profile]);
    }

    public function names(): array
    {
        return array_keys($this->clients);
    }
}
