<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle;

use Nowo\WhatsappBusinessBundle\DependencyInjection\Compiler\TransportServicePass;
use Nowo\WhatsappBusinessBundle\DependencyInjection\WhatsappBusinessExtension;
use Nowo\WhatsappBusinessBundle\Http\SensitiveDumpRegistrar;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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
    /**
     * Registers the compiler pass that checks the host transports of the `custom` profiles (REQ-ARCH-001 rule 5).
     *
     * It runs before optimization, so every host service definition and autowiring alias is already registered.
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new TransportServicePass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
    }

    /**
     * Registers the VarDumper casters that keep Cloud API secrets out of dumps.
     */
    public function boot(): void
    {
        parent::boot();
        SensitiveDumpRegistrar::register();
    }

    /**
     * Returns the bundle extension (nowo_whatsapp_business alias).
     */
    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof WhatsappBusinessExtension) {
            // @igor-ignore - Boot-time Symfony Bundle extension cache (not request state).
            $this->extension = new WhatsappBusinessExtension();
        }

        return $this->extension;
    }
}
