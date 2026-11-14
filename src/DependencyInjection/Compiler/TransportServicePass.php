<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\DependencyInjection\Compiler;

use LogicException;
use Nowo\WhatsappBusinessBundle\DependencyInjection\WhatsappBusinessExtension;
use Nowo\WhatsappBusinessBundle\Transport\CloudApiTransport;
use Nowo\WhatsappBusinessBundle\Transport\MockTransport;
use Nowo\WhatsappBusinessBundle\Transport\TransportInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function array_key_last;
use function class_exists;
use function is_a;
use function is_array;
use function is_string;
use function sprintf;
use function str_starts_with;

/**
 * Checks the host transports of the `custom` profiles (REQ-ARCH-001 rule 5): the service named in
 * `nowo_whatsapp_business.profiles.<name>.transport_service` must exist and implement {@see TransportInterface}.
 *
 * The extension records `[profile name => service id]` in {@see WhatsappBusinessExtension::TRANSPORT_SERVICES_PARAMETER}
 * and this pass consumes and removes that parameter, so it never reaches the compiled container. Unknown service ids
 * and classes that do not implement the port fail the build with a message naming the profile; a class that cannot be
 * known before compilation (a factory, a synthetic or a decorated definition) is left to the container's own type
 * checks, exactly like AuthKit's port pass.
 *
 * A custom profile must name a host service. A target that is, or aliases (at any depth), a service of this bundle
 * (`nowo_whatsapp_business.*`, such as another profile's `nowo_whatsapp_business.transport.<name>`), or whose class is
 * the bundle's own {@see CloudApiTransport} / {@see MockTransport}, fails the build: otherwise the profile would send
 * through another profile's token and phone number while its events report its own name.
 *
 * `cloud` and `mock` profiles are not touched.
 *
 * @internal
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class TransportServicePass implements CompilerPassInterface
{
    private const BUNDLE_SERVICE_PREFIX = 'nowo_whatsapp_business.';

    /**
     * Validates every custom transport declared by the extension.
     *
     * @throws LogicException when the service is missing or does not implement {@see TransportInterface}
     */
    public function process(ContainerBuilder $container): void
    {
        $parameter = WhatsappBusinessExtension::TRANSPORT_SERVICES_PARAMETER;
        if (!$container->hasParameter($parameter)) {
            return;
        }

        $services = $container->getParameter($parameter);
        $container->getParameterBag()->remove($parameter);
        if (!is_array($services)) {
            return;
        }

        foreach ($services as $profile => $serviceId) {
            if (!is_string($profile) || !is_string($serviceId)) {
                continue;
            }
            $this->assertTransport($container, $profile, $serviceId);
        }
    }

    /**
     * @throws LogicException when the service is missing, does not implement {@see TransportInterface} or is a
     *                        transport of this bundle
     */
    private function assertTransport(ContainerBuilder $container, string $profile, string $serviceId): void
    {
        if (!$container->hasDefinition($serviceId) && !$container->hasAlias($serviceId)) {
            throw new LogicException(sprintf('nowo_whatsapp_business.profiles.%s.transport_service names the service "%s", which is not defined.', $profile, $serviceId));
        }

        $chain = $this->aliasChain($container, $serviceId);
        foreach ($chain as $id) {
            if (str_starts_with($id, self::BUNDLE_SERVICE_PREFIX)) {
                throw new LogicException(sprintf('nowo_whatsapp_business.profiles.%s.transport_service: the service "%s" resolves to "%s", a service of this bundle. Name a host service: a custom profile cannot send through another profile\'s transport, token or phone number.', $profile, $serviceId, $id));
            }
        }

        $class = $this->classOf($container, $chain[array_key_last($chain)]);
        if ($class === null) {
            return;
        }
        if (!is_a($class, TransportInterface::class, true)) {
            throw new LogicException(sprintf('nowo_whatsapp_business.profiles.%s.transport_service: the service "%s" (%s) must implement "%s".', $profile, $serviceId, $class, TransportInterface::class));
        }
        foreach ([CloudApiTransport::class, MockTransport::class] as $bundleTransport) {
            if (is_a($class, $bundleTransport, true)) {
                throw new LogicException(sprintf('nowo_whatsapp_business.profiles.%s.transport_service: the service "%s" is a "%s", a transport of this bundle. Use `transport: cloud` or `transport: mock` on the profile instead.', $profile, $serviceId, $class));
            }
        }
    }

    /**
     * Returns $serviceId followed by every alias target up to the final id (stops on a cycle).
     *
     * @return non-empty-list<string>
     */
    private function aliasChain(ContainerBuilder $container, string $serviceId): array
    {
        $chain = [$serviceId];
        $id    = $serviceId;
        $seen  = [];
        while ($container->hasAlias($id) && !isset($seen[$id])) {
            $seen[$id] = true;
            $id        = (string) $container->getAlias($id);
            $chain[]   = $id;
        }

        return $chain;
    }

    /**
     * @return class-string|null null when the class cannot be known before the container is compiled
     */
    private function classOf(ContainerBuilder $container, string $id): ?string
    {
        if (!$container->hasDefinition($id)) {
            return null;
        }

        $definition = $container->getDefinition($id);
        if ($definition->getFactory() !== null || $definition->isSynthetic()) {
            return null;
        }

        $class = $container->getParameterBag()->resolveValue($definition->getClass() ?? $id);

        return is_string($class) && class_exists($class) ? $class : null;
    }
}
