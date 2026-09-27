<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle;

use Nowo\WhatsappBusinessBundle\DependencyInjection\WhatsappBusinessExtension;
use Nowo\WhatsappBusinessBundle\Http\SensitiveDumpRegistrar;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * WhatsApp Cloud API (Business) client, webhooks, and named profiles for Symfony.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class WhatsappBusinessBundle extends Bundle
{
    public function boot(): void
    {
        parent::boot();
        SensitiveDumpRegistrar::register();
    }

    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof WhatsappBusinessExtension) {
            // @igor-ignore - Boot-time Symfony Bundle extension cache (not request state).
            $this->extension = new WhatsappBusinessExtension();
        }

        return $this->extension;
    }
}
