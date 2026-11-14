<?php

declare(strict_types=1);

namespace Nowo\WhatsappBusinessBundle\DependencyInjection\Compiler;

use LogicException;
use Nowo\WhatsappBusinessBundle\DependencyInjection\WhatsappBusinessExtension;
use Nowo\WhatsappBusinessBundle\Transport\CloudApiTransport;
use Nowo\WhatsappBusinessBundle\Transport\MockTransport;
use Nowo\WhatsappBusinessBundle\Transport\TransportInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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
 * A custom profile must name a host service. A target that is, or leads to through aliases and child-definition
 * parents (at any depth), a service of this bundle (`nowo_whatsapp_business.*`, such as another profile's
 * `nowo_whatsapp_business.transport.<name>`), or that declares the bundle's own {@see CloudApiTransport} /
 * {@see MockTransport} class anywhere along that chain, fails the build: otherwise the profile would send
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

        $chain = $this->definitionChain($container, $serviceId);
        foreach ($chain as $id) {
            if (str_starts_with($id, self::BUNDLE_SERVICE_PREFIX)) {
                throw new LogicException(sprintf('nowo_whatsapp_business.profiles.%s.transport_service: the service "%s" resolves to "%s", a service of this bundle. Name a host service: a custom profile cannot send through another profile\'s transport, token or phone number.', $profile, $serviceId, $id));
            }
        }
        foreach ($chain as $id) {
            $declared = $this->declaredClassOf($container, $id);
            foreach ([CloudApiTransport::class, MockTransport::class] as $bundleTransport) {
                if ($declared !== null && is_a($declared, $bundleTransport, true)) {
                    throw new LogicException(sprintf('nowo_whatsapp_business.profiles.%s.transport_service: the service "%s" is a "%s", a transport of this bundle (declared on "%s"). Use `transport: cloud` or `transport: mock` on the profile instead.', $profile, $serviceId, $declared, $id));
                }
            }
        }

        $class = $this->classOf($container, $this->aliasTarget($container, $serviceId));
        if ($class !== null && !is_a($class, TransportInterface::class, true)) {
            throw new LogicException(sprintf('nowo_whatsapp_business.profiles.%s.transport_service: the service "%s" (%s) must implement "%s".', $profile, $serviceId, $class, TransportInterface::class));
        }
    }

    /**
     * Returns $serviceId followed by every id it leads to: alias targets and, for a child definition, its parent
     * (recursively, aliases resolved at each step). Stops on a cycle or an undefined id.
     *
     * @return non-empty-list<string>
     */
    private function definitionChain(ContainerBuilder $container, string $serviceId): array
    {
        $chain = [];
        $id    = $serviceId;
        $seen  = [];
        while (!isset($seen[$id])) {
            $seen[$id] = true;
            $chain[]   = $id;
            if ($container->hasAlias($id)) {
                $id = (string) $container->getAlias($id);
                continue;
            }
            if (!$container->hasDefinition($id)) {
                break;
            }
            $definition = $container->getDefinition($id);
            if (!$definition instanceof ChildDefinition) {
                break;
            }
            $id = $definition->getParent();
        }

        return $chain;
    }

    /**
     * Follows aliases only (the definition the container will instantiate, before child definitions are resolved).
     */
    private function aliasTarget(ContainerBuilder $container, string $serviceId): string
    {
        $id   = $serviceId;
        $seen = [];
        while ($container->hasAlias($id) && !isset($seen[$id])) {
            $seen[$id] = true;
            $id        = (string) $container->getAlias($id);
        }

        return $id;
    }

    /**
     * Returns the class literally declared on the definition $id (factory definitions included), if it exists.
     *
     * @return class-string|null
     */
    private function declaredClassOf(ContainerBuilder $container, string $id): ?string
    {
        if (!$container->hasDefinition($id)) {
            return null;
        }
        $declared = $container->getDefinition($id)->getClass();
        if ($declared === null) {
            return null;
        }
        $class = $container->getParameterBag()->resolveValue($declared);

        return is_string($class) && class_exists($class) ? $class : null;
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
