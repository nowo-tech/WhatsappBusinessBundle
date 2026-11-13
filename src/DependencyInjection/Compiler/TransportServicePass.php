<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\DependencyInjection\Compiler;

use LogicException;
use Nowo\WhatsappBusinessBundle\DependencyInjection\WhatsappBusinessExtension;
use Nowo\WhatsappBusinessBundle\Transport\TransportInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function class_exists;
use function is_a;
use function is_array;
use function is_string;
use function sprintf;

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
 * `cloud` and `mock` profiles are not touched.
 *
 * @internal
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class TransportServicePass implements CompilerPassInterface
{
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
     * @throws LogicException when the service is missing or does not implement {@see TransportInterface}
     */
    private function assertTransport(ContainerBuilder $container, string $profile, string $serviceId): void
    {
        if (!$container->hasDefinition($serviceId) && !$container->hasAlias($serviceId)) {
            throw new LogicException(sprintf('nowo_whatsapp_business.profiles.%s.transport_service names the service "%s", which is not defined.', $profile, $serviceId));
        }

        $class = $this->classOf($container, $serviceId);
        if ($class !== null && !is_a($class, TransportInterface::class, true)) {
            throw new LogicException(sprintf('nowo_whatsapp_business.profiles.%s.transport_service: the service "%s" (%s) must implement "%s".', $profile, $serviceId, $class, TransportInterface::class));
        }
    }

    /**
     * @return class-string|null null when the class cannot be known before the container is compiled
     */
    private function classOf(ContainerBuilder $container, string $serviceId): ?string
    {
        $id   = $serviceId;
        $seen = [];
        while ($container->hasAlias($id) && !isset($seen[$id])) {
            $seen[$id] = true;
            $id        = (string) $container->getAlias($id);
        }
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
